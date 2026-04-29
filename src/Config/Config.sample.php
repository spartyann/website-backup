<?php

namespace Config;

// Copy File into "Config.php"
// Rename Config____Sample  => Config


class Config____Sample
{
	public const DEBUG = true;
	private static bool $VERBOSE = false; // Default value

	public static function VERBOSE() : bool{
		return self::$VERBOSE;
	}

	public static function DEFINE_VERBOSE(bool $verbose){
		return self::$VERBOSE = $verbose;
	}

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

	// Passord for UI
	public const UI_PASSWORD = 'xxxxxxxxxxxx';

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

						'user' => 'xxxxxxxxxxxxx@ik.me',
						'password' => 'xxxxxxxxxxxxxxxxxx',
						'encryption' => 'ssl',
						'host' => 'imap.example.com',
						'port' => 993,

						'mail_boxes' => ['INBOX', 'Sent'],
						//'mail_boxes' => null, //['INBOX', 'Sent'],
						'mails_sync_dir' => dirname(dirname(__DIR__)) . '/tests/mails',
						'php_command' => null, // 'php' or '/bin/php8.1' or null to auto detect
						'optim_mem' => false,
						'verbose' => false,
					],
					
				]
			],

			"docs" => [
				'prefix' => 'docs_',
				'send_s3' => false,
				'items' => [
					[
						'type' => 'google_drive',
						'mode' => 'export_all_doc',
						'types_to_export' => [ 'document', 'spreadsheet', 'presentation' ],
						'files_sync_dir' => dirname(dirname(__DIR__)) . '/tests/docs', // Local dir to sync with Google Drive
						'google_auth' => [ // Google API Auth
							"type" => "service_account",
							"project_id" => "xxxxxxxx",
							"private_key_id" => "xxxxxxxxxxxxxx",
							"private_key" => "xxxxxxxxxxxxxx",
							"client_email" => "xxxxxxxxxxxxxx",
							"client_id" => "xxxxxxxxxxxxxx",
							"auth_uri" => "https://accounts.google.com/o/oauth2/auth",
							"token_uri" => "xxxxxxxxxxxxxx",
							"auth_provider_x509_cert_url" => "https://www.googleapis.com/oauth2/v1/certs",
							"client_x509_cert_url" => "xxxxxxxxxxxxxx",
							"universe_domain" => "googleapis.com"
						],

						'backup_dir' => null, // dir in ZIP. Keep Null to not include in ZIP
					],
				]
			],

		];
	}


	// Download DB API
	public static function downloadDBApi() {
		return [
			'my_site' => [
				'type' => 'db',
				'host' => 'localhost',
				'port' => 3306,
				'user' => 'root',
				'pwd' => '',
				'db_name' => 'DB1',
				'tables' => ['table1', 'table2'] // null for all tables
			],
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


	public const NOTIF_TELEGRAM_CHAT_ID = null;
	public const NOTIF_TELEGRAM_TOKEN = null;
	public const NOTIF_ERROR_TELEGRAM_CHAT_ID = null;
	public const NOTIF_ERROR_TELEGRAM_TOKEN = null;
}

