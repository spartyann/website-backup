<?php

namespace App;

use App\Notifications\DiscordHelper;
use App\S3\S3Manager;
use App\Tools\FileTools;
use Config\Config;
use Ifsnop\Mysqldump\Mysqldump;

class Backup 
{
    public static function run()
	{
		try
		{
			$tmpDir = FileTools::prepareTempDir();
			$notifMessage = '';

			$now = new \DateTime();

			//$now = $now->sub( new \DateInterval("P2M"));

			$sfx = $now->format('Y-m-d__H-i-s');

			$dumpFile = null;

			$backupExt = Config::COMPRESSION_TYPE == 'tar' ? '.tar': '.zip';

			$backupFile = Config::localStorageBackupDir() . '/backup_' . $sfx . $backupExt;
			$tempBackupFile = $tmpDir . '/backup' . $backupExt;

		
			// Database
			if (Config::DB_ENABLED)
			{
				$dumpFile = $tmpDir . '/dump.sql';
				self::dumpDb($dumpFile);
			}
		
			// Compress with Files
			if (Config::COMPRESSION_TYPE == 'phpzip')
			{
				FileTools::makePhpZip($tempBackupFile,
					$dumpFile == null ? []: [ $dumpFile ],
					Config::FILES_ENABLED ? Config::filesDirs() : []
				);
			}
			else if (Config::COMPRESSION_TYPE == 'tar')
			{
				FileTools::tar($tempBackupFile,
					$dumpFile == null ? []: [ $dumpFile ],
					Config::FILES_ENABLED ? Config::filesDirs() : []
				);
			}

			// Copy File
			rename($tempBackupFile, $backupFile);
			$size = FileTools::getReadableSize(filesize($backupFile));

			$notifMessage .=  'File: ' . basename($backupFile) . ' (' . $size . ')' . "\n";


			// Send S3
			if (Config::S3_ENABLED)
			{
				$s3Key = Config::S3_DIR . '/' . basename($backupFile);
				S3Manager::put($s3Key, $backupFile);
				$notifMessage .= 'Copied to S3: ' . $s3Key;
			}

			// Delete old Backup
			self::removeOldBackup($notifMessage);


			// Notify
			if (Config::NOTIF_DISCORD_WEBHOOK_URL != null)
			{
				DiscordHelper::sendMessage('Backup completed', DiscordHelper::escape($notifMessage), '#00ff00');
			}

		}
		catch (\Exception $ex)
		{
			$msg = $ex->getMessage();
			$msg .= "\n" . $ex->getTraceAsString();

			// Notify
			if (Config::NOTIF_ERROR_DISCORD_WEBHOOK_URL != null)
			{
				DiscordHelper::sendMessage('Backup ERROR', DiscordHelper::escape($msg), '#ff0000', Config::NOTIF_ERROR_DISCORD_WEBHOOK_URL);
			}

			if (Config::DEBUG) throw $ex;
		}


		return $backupFile;
	}

	private static function removeOldBackup(string &$notifMessage)
	{
		if (Config::LOCAL_BACKUP_RETENTION != null)
		{
			$files = self::filterFiles(
				glob(Config::localStorageBackupDir() . '/*.*')
				, Config::LOCAL_BACKUP_RETENTION);

			foreach($files as $file)
			{
				unlink($file);
				$notifMessage .= "\nDeleted: $file";
			}
		}

		if (Config::S3_ENABLED && Config::S3_BACKUP_RETENTION != null)
		{
			$files = array_map(function($item) { return $item['file']; }, S3Manager::getAllS3Files());

			$files = self::filterFiles(
				$files
				, Config::S3_BACKUP_RETENTION);

			foreach($files as $file)
			{
				S3Manager::delete($file);
				$notifMessage .= "\nDeleted S3: $file";
			}
		}
	}


	private static function filterFiles(array $files, string $retention)
	{
		$backupRetentionTimes = new \DateInterval($retention);

		$maxDate = new \DateTime();
		$maxDate = $maxDate->sub($backupRetentionTimes);

		$res = [];

		foreach($files as $file)
		{
			
			$matches = [];
			//Y-m-d__H-i-s
			if (preg_match("/([0-9]+)-([0-9]+)-([0-9]+)__([0-9]+)-([0-9]+)-([0-9]+)\.[a-z]+/", $file, $matches) !== 1) continue;

			
			$y = $matches[1];
			$m = $matches[2];
			$d = $matches[3];

			$h = $matches[4];
			$i = $matches[5];
			$s = $matches[6];
			
			$date = new \DateTime("$y-$m-$d" . "T$h:$i:$s");

			if ($date <= $maxDate)
			{
				//if (Config::DEBUG == false) unlink($file);

				$res[] = $file;
			}
		}

		return $res;
	}


	public static function dumpDb($dumpFile)
	{
		$mysqlHost = Config::DB_HOST;
		$mysqlPort = Config::DB_PORT;
		$mysqlDbname = Config::DB_DATABASE;
		$mysqlUser = Config::DB_USER;
		$mysqlPwd = Config::DB_PWD;
		$dumpSettings = Config::DB_DUMP_SETTINGS;

		$dump = new Mysqldump("mysql:host=$mysqlHost:$mysqlPort;dbname=$mysqlDbname", $mysqlUser, $mysqlPwd, $dumpSettings);
		$dump->start($dumpFile);
	}




}
