<?php

namespace App;

use App\Notifications\DiscordHelper;
use App\Notifications\SlackHelper;
use App\S3\S3Manager;
use App\Tools\CommandTools;
use App\Tools\FileTools;
use Config\Config;
use Exception;
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


			// Dump data base and prepare static dirs in $
			$dumpFiles = [];
			$staticDirs = [];
			foreach(Config::items() as $item)
			{
				if ($item['type'] == 'db')
				{
					$dumpFile = $tmpDir . '/' . $item['file_name'];
					self::dumpDb($dumpFile, $item, $notifMessage);

					$dumpFiles[] = $dumpFile;
				}

				if ($item['type'] == 'dir') $staticDirs[] = $item;
			}

			// Compress with Files
			if (Config::COMPRESSION_TYPE == 'phpzip')
			{
				FileTools::makePhpZip($tempBackupFile, $dumpFiles, $staticDirs);
			}
			else if (Config::COMPRESSION_TYPE == 'tar')
			{
				FileTools::tar($tempBackupFile, $dumpFiles, $staticDirs);
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

			// Notify
			if (Config::NOTIF_SLACK_WEBHOOK_URL != null)
			{
				SlackHelper::sendMessage('Backup completed', SlackHelper::escape($notifMessage));
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

			if (Config::NOTIF_ERROR_SLACK_WEBHOOK_URL != null)
			{
				SlackHelper::sendMessage('Backup ERROR', SlackHelper::escape($msg), Config::NOTIF_ERROR_SLACK_WEBHOOK_URL);
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


	public static function dumpDb($dumpFile, array $item, string &$notifMessage)
	{
		$mysqlHost = $item['host'];
		$mysqlPort = $item['port'];
		$mysqlDbname = $item['db_name'];
		$mysqlUser = $item['user'];
		$mysqlPwd = $item['pwd'];

		if (Config::DB_USE_MYSQLDUMP_CMD)
		{
			// mysqldump --routines --add-drop-table --user="$MARIADB_USER" --host="$MARIADB_HOST" --password="$MARIADB_PASSWORD" "$MARIADB_DB" --result-file="$MARIADB_FILE"
			// --add-drop-trigger
			// mysql --user="$MARIADB_USER" --host="$MARIADB_HOST" --password="$MARIADB_PASSWORD" "$MARIADB_DB" < "$MARIADB_FILE"

			$cmdOptions = [
				'routines' => true,
				'add-drop-table' => true,
				'host' => $mysqlHost,
				'user' => $mysqlUser,
				'password' => $mysqlPwd,
				'port' => $mysqlPort
			];

			// Override
			foreach(Config::DB_MYSQLDUMP_VARIABLES as $name => $val) $cmdOptions[$name] = $val;

			// Force result-file
			$cmdOptions['result-file'] = $dumpFile;
			$cmdOptions['protocol'] = 'tcp';

			$cmd = 'mysqldump ';
			foreach($cmdOptions as $name => $value)
			{
				if ($value === true) $value = 'TRUE';
				if ($value === false) $value = 'FALSE';
				if (is_numeric($value)) $value = '' . $value;

				if (is_string($value) == false) throw new Exception("Command param $name is not a string.");

				$cmd .= ' --' . $name . '=' . escapeshellarg($value);
			}

			$cmd .= ' ' . escapeshellarg($mysqlDbname);
			
			CommandTools::exec($cmd, 'Error on mysqldump: ', $output);

			if (count($output) > 0) $notifMessage .= "\n" . implode("\n", $output);
		}
		else // USE Mysqldump Class
		{
			$settings = [
				'add-drop-database' => false,
				'add-drop-table' => true,
				'add-drop-trigger' => true,
			];

			foreach(Config::DB_DUMP_LIB_SETTINGS as $name => $val) $settings[$name] = $val;

			$dump = new Mysqldump("mysql:host=$mysqlHost:$mysqlPort;dbname=$mysqlDbname", $mysqlUser, $mysqlPwd, $settings);
			$dump->start($dumpFile);
		}


	}




}
