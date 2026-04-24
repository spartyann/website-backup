<?php

if (php_sapi_name() !== 'cli') {
	echo "This file is not meant to be called directly.";
	exit(1);
}

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Tools\FileTools;
use DirectoryTree\ImapEngine\Enums\ImapFetchIdentifier;
use DirectoryTree\ImapEngine\Exceptions\ImapCommandException;
use DirectoryTree\ImapEngine\Exceptions\ImapConnectionException;
use DirectoryTree\ImapEngine\Mailbox;

function progressBar(int $current, int $total, string $label = ''): void
{
	$data = json_encode([
        'type'    => 'progress',
        'current' => $current,
        'total'   => $total,
        'label'   => $label
    ]);
    echo $data . "\n"; // \n obligatoire pour fgets()
    // flush() inutile en CLI, PHP flush automatiquement sur \n
}

function sendLog(string $message): void
{
    echo json_encode(['type' => 'log', 'message' => $message]) . "\n";
}

function sendException(string $message, $trace = []): void
{
    echo json_encode(['type' => 'exception', 'message' => $message, 'trace' => $trace]) . "\n";
	exit(1);
}

try {

	if (PHP_OS_FAMILY === 'Windows') {
		$argv = array_map(function($a) { return str_replace('----EX----', '!', $a); }, $argv);
	}

	$host = $argv[1];
	$port = $argv[2];
	$user = $argv[3];
	$password = $argv[4];
	$encryption = $argv[5];
	$mailBoxesNames = json_decode($argv[6], true);
	$mailsSyncDir = $argv[7];
	$verbose = $argv[8] == '1';
	$minverbose = $argv[9] == '1';

	$mailbox = new Mailbox([
		'host' => $host,
		'port' => $port,
		'username' => $user,
		'password' => $password,
		'encryption' => $encryption,
	]);

	try {
		$mailbox->connect();
	} catch (ImapCommandException $e) {
		throw $e;
		// Handle authentication failures (invalid credentials).
	} catch (ImapConnectionException $e) {
		throw $e;
		// Handle connection failures (network, server issues).
	}

	if ($mailBoxesNames == null || count($mailBoxesNames) == 0)
	{
		$boxes = $mailbox->folders()->get();

		$mailBoxesNames = [];
		foreach ($boxes as $box)
		{
			$mailBoxesNames[] = $box->path();
		}
	}

	file_put_contents($mailsSyncDir . '/email_boxes.txt', json_encode($mailBoxesNames, JSON_PRETTY_PRINT));


	$boxDirs = [];
	$boxDirNames = [];

	foreach ($mailBoxesNames as $mailBoxName)
	{
		gc_collect_cycles();

		$box = $mailbox->folders()->find($mailBoxName);
		if ($box == null) throw new \Exception('Mail Box Not Found: ' . $mailBoxName);

		$boxDirName = $box->name();
		$boxDir = $mailsSyncDir . '/' . $boxDirName;
		$boxDirs[] = $boxDir;
		$boxDirNames[] = $boxDirName;

		if (is_dir($boxDir) == false) mkdir($boxDir);

		if ($verbose) echo "\n********** Sync Mail Box: $user => " . $box->name() . " ********** - " . (round(memory_get_peak_usage() / 1_048_576, 2) . ' MB') . "\n";

		$messages = $box->messages()->withHeaders()->get();
			
		file_put_contents($boxDir . '/all_emails.txt', json_encode($messages, JSON_PRETTY_PRINT));

		//dd($messages);
		// Get existing Files
		$existingEmailFiles = array_filter(scandir($boxDir), function($name){ return str_starts_with($name,'email_'); });

		$emailFileNames = [];

		$messagesToDownload = [];
		$ids = [];

		foreach ($messages as $messageOverview)
		{
			$dateString = '';

			if ($messageOverview->date() == null) {
				if ($verbose) {
					echo "\nEmail with no date: " . $messageOverview->uid();
					echo "\nMessage from: " . json_encode($messageOverview->from());
				}
				$dateString = 'NO-DATE';
			} else {
				$date = new \DateTime();
				$date->setTimestamp($messageOverview->date()->getTimestamp());
				$dateString = $date->format('Y-m-d_H-i');
			}

			$subject = $messageOverview->subject();

			if ($subject === null){
				$subject = '';
				if ($verbose) {
					echo "\nNO SUBJECT in message: " . $messageOverview->uid();
					echo "\nMessage from: " . json_encode($messageOverview->from());
				}
			}
			
			$emailFileName = 'email_' . $messageOverview->uid()
				. '_' . $dateString
				. '-' . FileTools::cleanupFileChars(substr($subject, 0, 50))
				. '.eml';

			$emailFileNames[$emailFileName] = $emailFileName;

			if (in_array($emailFileName, $existingEmailFiles)) continue;


			$messagesToDownload[] = [
				'uid' => $messageOverview->uid(),
				'filename' => $emailFileName,
				'subject' => $subject,
			];

			$ids[] = $messageOverview->uid();
		}

		unset($messages);

		/*dd($ids, $box->messages()
			->withHeaders()
			->withBody()
			->where('UID', implode(',', $ids))
			->get());*/

		foreach ($messagesToDownload as $index => $messageIdToDownload)
		{
			$subject = $messageIdToDownload['subject'];
			$uid = $messageIdToDownload['uid'];
			$emailFileName = $messageIdToDownload['filename'];

			if ($verbose) {
				progressBar($index + 1, count($messagesToDownload));
				//echo "\nDownloaded email with ID: " . $uid . " - Subject: " . $subject;
			}

			$message = $box->messages()->withHeaders()->withFlags()->withBody()->find($uid, ImapFetchIdentifier::Uid);

			$eml = $message->head() . "\r\n" . $message->body();

			//throw new \Exception("Test exception for email with subject: " . $subject);
			file_put_contents($boxDir . "/" . $emailFileName, $eml);

			unset($eml);
			unset($message);
		}

		foreach ($existingEmailFiles as $fileName)
		{
			if (isset($emailFileNames[$fileName]) == false)
			{
				if ($verbose) echo "\nRemove email file: " . $fileName;
				unlink($boxDir . "/" . $fileName);
			}
		}
		
		unset($box);
	}

	$mailbox->disconnect();
	unset($mailbox);

	// Remove deleted folders
	$existingDirectories = FileTools::getAllSubDirectories($mailsSyncDir);

	foreach ($existingDirectories as $existingDirectory)
	{
		// Check if has not be deleted  134 217 728
		if (is_dir($existingDirectory) == false) continue;

		// Remove not found
		if (in_array($existingDirectory, $boxDirs) == false) FileTools::removeDir($existingDirectory);
	}


}catch (\Throwable $e) {
	sendException($e->getMessage(), $e->getTrace());
	exit(1);
}