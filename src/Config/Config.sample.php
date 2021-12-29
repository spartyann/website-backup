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

	// Database
	public const DB_ENABLED = true;
	public const DB_HOST = 'localhost';
	public const DB_PORT = 3306;
	public const DB_USER = 'mydb';
	public const DB_PWD = 'yuj4f6ghj514d6fj516gh51sdgh';
	public const DB_DATABASE = 'mydb';

	// Files
	public const FILES_ENABLED = true;
	public static function filesDir() { return dirname(dirname(__DIR__)) . '/my_website'; } 

	//
	// Backup options
	//

	// Backup
	public const COMPRESSION_TYPE = 'tar'; // zip, tar, phpzip   => Use "tar" to preserve permissions and owner files
	
	// Local Storage
	public static function localStorageBackupDir() { return dirname(dirname(__DIR__)) . '/backups'; } 
	public const LOCAL_BACKUP_RETENTION = 'P1M'; // Put null to disable automatic deletion

	// S3
	public const S3_ENABLED = false;
	public const S3_REGION = 'eu-west-3';
	public const S3_ENDPOINT = '';
	public const S3_ACCESS_KEY_ID = '';
	public const S3_SECRET_ACCESS_KEY = '';
	public const S3_BUCKET = 'my-bucket';
	public const S3_BACKUP_RETENTION = 'P1M'; // Put null to disable automatic deletion

	// Notifications
	public const DISCORD_WEBHOOK_URL = null;

}

