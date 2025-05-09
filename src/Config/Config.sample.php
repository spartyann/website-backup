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

	// When calling by URL provide this token. ?token=xxx
	public const URL_TOKEN = 'xxxxxxxxxxxx';


	// Items to Backup
	public static function groups() {
		return [
			//'<nom du groupe>' => [ <Paramètres du groupe> ]
			'my_site' => [
				'prefix' => 'my_site_', // Préfixe du fichier
				'suffix' => '', // Suffixe du fichier
				'send_s3' => true, // Envoi sur S3 si configuré
				'items' => [ // Eléments à sauvegarder
					[
						'type' => 'db',
						'host' => 'localhost',
						'port' => 3306,
						'user' => 'root',
						'pwd' => '',
						'db_name' => 'DB1',
						'file_name' => 'DB1.sql' // Name file in ZIP
					],
					[
						'type' => 'dir',
						'backup_dir' => 'files1', // dir in ZIP
						'dir' => dirname(dirname(__DIR__)) . '/tests/site_test'
					],
				]
			],
			//'<nom du groupe>' => [ <Paramètres du groupe> ]
			'mails' => [
				'prefix' => 'mails_',
				'send_s3' => true,
				'items' => [
					[
						'type' => 'mail_imap',
						'backup_dir' => 'mails', // dir in ZIP
						'mailbox' => '{mail.infomaniak.com:993/imap/ssl}', // Server or Path
						'user' => 'xxxxxxxxxxxxx@ik.me',
						'password' => 'xxxxxxxxxxxxxxxxxx',
						'retries' => 0,
						'options' => null,
						'mail_boxes' => ['INBOX', 'Sent'],
						//'mail_boxes' => null, //['INBOX', 'Sent'],
						'mails_sync_dir' => dirname(dirname(__DIR__)) . '/tests/mails'
					],
					
				]
			]

		];
	}

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
	public const NOTIF_ERROR_TITLE = null;
	public const NOTIF_TITLE = null;
	
	public const NOTIF_DISCORD_USERNAME = 'Cron job';
	public const NOTIF_DISCORD_WEBHOOK_URL = null;
	public const NOTIF_ERROR_DISCORD_WEBHOOK_URL = null;

	public const NOTIF_SLACK_WEBHOOK_URL = null;
	public const NOTIF_ERROR_SLACK_WEBHOOK_URL = null;
}

