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
		}


		return $backupFile;
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
