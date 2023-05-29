<?php

namespace Config;

// Copy File into "Config.php"
// Rename Config____Sample  => Config


class Config____Sample
{
	public const DEBUG = true;

	//
	// Backup objects
	//

	// Database options
	public const DB_USE_MYSQLDUMP_CMD = false;
	public const DB_MYSQLDUMP_VARIABLES = [
		'triggers' => 'TRUE'
	]; // Use command:  [ mysqldump --help ] to see all variables options
	public const DB_DUMP_LIB_SETTINGS = []; // Dump Settings for Ifsnop\Mysqldump\Mysqldump

	// Items to Backup

	public static function items() { return [
		[
			'type' => 'db',
			'host' => 'localhost',
			'port' => 3306,
			'user' => 'mydb',
			'pwd' => 'yuj4f6ghj514d6fj516gh51sdgh',
			'db_name' => 'mydb',
			'file_name' => 'mydb.sql' // Name file in ZIP
		],
		[
			'type' => 'db',
			'host' => 'localhost',
			'port' => 3306,
			'user' => 'mydb',
			'pwd' => 'yuj4f6ghj514d6fj516gh51sdgh',
			'db_name' => 'mydb',
			'file_name' => 'mydb2.sql' // Name file in ZIP
		],
		[
			'type' => 'dir',
			'backup_dir' => 'files1', // dir in ZIP
			'dir' => dirname(dirname(__DIR__)) . '/tests/site_test'
		],
		[
			'type' => 'dir',
			'backup_dir' => 'files2', // dir in ZIP
			'dir' => dirname(dirname(__DIR__)) . '/tests/site_test'
		]
	]; }

	//
	// Backup options
	//

	// Backup
	public const COMPRESSION_TYPE = 'tar'; // tar, phpzip   => Use "tar" to preserve permissions and owner files
	
	// Local Storage
	public static function localStorageBackupDir() { return dirname(dirname(__DIR__)) . '/backups'; } // Do NOT put / at the end
	public const LOCAL_BACKUP_RETENTION = 'P1M'; // Put null to disable automatic deletion

	// S3
	public const S3_ENABLED = false;
	public const S3_REGION = 'eu-west-3';
	public const S3_ENDPOINT = '';
	public const S3_ACCESS_KEY_ID = '';
	public const S3_SECRET_ACCESS_KEY = '';
	public const S3_BUCKET = 'my-bucket';
	public const S3_DIR = 'dir';
	public const S3_BACKUP_RETENTION = 'P1M'; // Put null to disable automatic deletion

	// Notifications
	public const NOTIF_DISCORD_USERNAME = 'Cron job';
	public const NOTIF_DISCORD_WEBHOOK_URL = null;
	public const NOTIF_ERROR_DISCORD_WEBHOOK_URL = null;

	public const NOTIF_SLACK_WEBHOOK_URL = null;
	public const NOTIF_ERROR_SLACK_WEBHOOK_URL = null;
}

