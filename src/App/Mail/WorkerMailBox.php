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

	function execStream(string $command): int
	{
		$handle = popen("{$command} 2>&1", 'r');

		if ($handle === false) {
			throw new \RuntimeException("Impossible d'ouvrir le process : {$command}");
		}

		while (!feof($handle)) {
			$line = fgets($handle);
			if ($line === false) continue;

			$data = json_decode(trim($line), true);

			// Ligne JSON valide
			if (json_last_error() === JSON_ERROR_NONE && isset($data['type'])) {
				match ($data['type']) {
					'progress' => progressBar(
						$data['current'],
						$data['total'],
						$data['label']
					),
					'log' => sendLog($data['message']), // ignorer ou loguer dans un fichier
					'exception' => sendException($data['message'], $data['trace']),
					default => null,
				};
			} else {
				// Ligne non-JSON (erreur PHP, exception...) → afficher en rouge
				//echo "\n\033[31m{$line}\033[0m";
				echo $line;
				flush(); // force l'affichage immédiat en CLI
			}
		}

		$exitCode = pclose($handle);
		return $exitCode;
	}

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

		if ($verbose) echo "\n********** Sync Mail Box: $user => " . $box->name() . " **********\n";

		$args = [];
		$args[] = $host;
		$args[] = $port;
		$args[] = $user;
		$args[] = $password;
		$args[] = $encryption;
		$args[] = json_encode($mailBoxesNames);
		$args[] = $mailsSyncDir;
		$args[] = $mailBoxName;
		$args[] = $verbose ? '1' : '0';
		$args[] = '1'; // Minimum Verbose

		if (PHP_OS_FAMILY === 'Windows') {
			$args = array_map(function($a) { return str_replace('!', '----EX----', $a); }, $args);
		}

		$args = array_map(function($a) { return escapeshellarg($a); }, $args);

		$php = $phpCommand ?? PHP_BINARY;
		$script = escapeshellarg(__DIR__ . '/WorkerMessages.php');
		
		$command = "{$php} {$script} " . implode(' ', $args);
		//$res = shell_exec($command);

		$resStream = execStream($command);

		if ($resStream !== 0) {
			echo "Error executing WorkerMessages.php for box $mailBoxName. Exit code: {$resStream}\n";
			exit(1);
		}
		//************* */

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