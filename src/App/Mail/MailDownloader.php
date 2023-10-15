<?php

namespace App\Mail;

use App\Tools\FileTools;

class MailDownloader 
{

	public static function downloadMails(
		string $mailbox,
		string $user,
		string $password,
		int $retries = null,
		array $options = null,
		array $mailBoxesNames = null,
		string $mailsSyncDir
	)
	{
		if (is_dir($mailsSyncDir) == false) mkdir($mailsSyncDir);

		$co = imap_open($mailbox, $user, $password, OP_READONLY, $retries, $options ?? []);
		
		if ($co == false) throw new \Exception("Unable to IMAP login: " . imap_last_error());

		$mailBoxesDesc = imap_getmailboxes($co, $mailbox, '*');

		if ($mailBoxesNames == null) $mailBoxesNames =  imap_list($co, $mailbox, '*');
		else $mailBoxesNames = array_map(function($item) use($mailbox) { return $mailbox . $item; }, $mailBoxesNames);

		file_put_contents($mailsSyncDir . '/email_boxes.txt', json_encode($mailBoxesDesc, JSON_PRETTY_PRINT));

		$boxDirs = [];
		$boxDirNames = [];

		foreach ($mailBoxesNames as $box)
		{
			imap_close($co);
			$co = imap_open($box, $user, $password, OP_READONLY, $retries, $options ?? []);

			if (1 !== preg_match("/(\{[^\}]+\})(.+)/i", $box, $matches)) throw new \Exception('Invalid Mail Box Name: ' . $box);

			$boxDirName = $matches[2];
			$boxDir = $mailsSyncDir . '/' . $boxDirName;
			$boxDirs[] = $boxDir;
			$boxDirNames[] = $boxDirName;

			if (is_dir($boxDir) == false) mkdir($boxDir);

			// Get Num mails
			$numMails = imap_num_msg($co);

			// Has mails ?
			if ($numMails == 0) continue; // NO MAILS

			// Get overview
			$overviews = imap_fetch_overview($co, "1:$numMails" );

			file_put_contents($boxDir . '/all_emails.txt', json_encode($overviews, JSON_PRETTY_PRINT));

			// Get existing Files
			$existingEmailFiles = array_filter(scandir($boxDir), function($name){ return str_starts_with($name,'email_'); });

			$emailFileNames = [];

			foreach ($overviews as $overview)
			{
				$udate = new \DateTime();
				$udate->setTimestamp($overview->udate);
				
				$emailFileName = 'email_' . $overview->uid
					. '_' . $udate->format('Y-m-d_H-i')
					. '-' . FileTools::cleanupFileChars(substr($overview->subject, 0, 50))
					. '.eml';

				$emailFileNames[$emailFileName] = $emailFileName;

				if (in_array($emailFileName, $existingEmailFiles)) continue;

				//echo "\nDownload email: " . $overview->subject;

				imap_savebody($co, $boxDir . "/" . $emailFileName, $overview->msgno);
			}

			foreach ($existingEmailFiles as $fileName)
			{
				if (isset($emailFileNames[$fileName]) == false)
				{
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
