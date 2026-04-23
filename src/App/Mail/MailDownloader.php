<?php

namespace App\Mail;

use App\Tools\FileTools;
use DirectoryTree\ImapEngine\Enums\ImapFetchIdentifier;
use DirectoryTree\ImapEngine\Exceptions\ImapCommandException;
use DirectoryTree\ImapEngine\Exceptions\ImapConnectionException;
use DirectoryTree\ImapEngine\Mailbox;

class MailDownloader 
{

	public static function downloadMails(
		string $host,
		string $port,
		string $user,
		string $password,
		string $encryption,
		?int $retries,
		?array $mailBoxesNames,
		string $mailsSyncDir
	)
	{
		if (is_dir($mailsSyncDir) == false) mkdir($mailsSyncDir);

		//https://imapengine.com/docs/usage/folders

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

		$boxes = [];

		if ($mailBoxesNames == null || count($mailBoxesNames) == 0)
		{
			$boxes = $mailbox->folders()->get();
		}
		else
		{
			foreach ($mailBoxesNames as $mailBoxName)
			{
				$box = $mailbox->folders()->find($mailBoxName);
				if ($box == null) throw new \Exception('Mail Box Not Found: ' . $mailBoxName);

				$boxes[] = $box;
			}
		}

		
		file_put_contents($mailsSyncDir . '/email_boxes.txt', json_encode($boxes, JSON_PRETTY_PRINT));
		

		$boxDirs = [];
		$boxDirNames = [];

		foreach ($boxes as $box)
		{
			
			$boxDirName = $box->name();
			$boxDir = $mailsSyncDir . '/' . $boxDirName;
			$boxDirs[] = $boxDir;
			$boxDirNames[] = $boxDirName;

			if (is_dir($boxDir) == false) mkdir($boxDir);

			$messages = $box->messages()->withHeaders()->get();

			
			
			file_put_contents($boxDir . '/all_emails.txt', json_encode($messages, JSON_PRETTY_PRINT));
			
			//dd($messages);
			// Get existing Files
			$existingEmailFiles = array_filter(scandir($boxDir), function($name){ return str_starts_with($name,'email_'); });

			$emailFileNames = [];

			foreach ($messages as $messageOverview)
			{
				//dd($message);
				$date = new \DateTime();
				$date->setTimestamp($messageOverview->date()->getTimestamp());

				$subject = $messageOverview->subject();
				
				$emailFileName = 'email_' . $messageOverview->uid()
					. '_' . $date->format('Y-m-d_H-i')
					. '-' . FileTools::cleanupFileChars(substr($subject, 0, 50))
					. '.eml';

				$emailFileNames[$emailFileName] = $emailFileName;

				if (in_array($emailFileName, $existingEmailFiles)) continue;

				//echo "\nDownload email: " . $subject;

				$message = $box->messages()->withHeaders()->withFlags()->withBody()->find($messageOverview->uid(), ImapFetchIdentifier::Uid);
	
				$eml = $message->head() . "\r\n" . $message->body();

				file_put_contents($boxDir . "/" . $emailFileName, $eml);
			}

			foreach ($existingEmailFiles as $fileName)
			{
				if (isset($emailFileNames[$fileName]) == false)
				{
					echo "\nRemove email file: " . $fileName;
					unlink($boxDir . "/" . $fileName);
				}
			}

		}

		// Remove deleted folders
		$existingDirectories = FileTools::getAllSubDirectories($mailsSyncDir);

		foreach ($existingDirectories as $existingDirectory)
		{
			// Check if has not be deleted
			if (is_dir($existingDirectory) == false) continue;

			// Remove not found
			if (in_array($existingDirectory, $boxDirs) == false) FileTools::removeDir($existingDirectory);
		}
	}

}
