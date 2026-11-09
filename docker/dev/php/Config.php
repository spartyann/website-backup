<?php
namespace Config;

require_once '/workspace/src/Config/Config.sample.php';
foreach (['UI_PASSWORD', 'API_KEY', 'URL_TOKEN', 'DB_PASSWORD'] as $name) {
    $value = getenv('WB_DEV_' . $name);
    if ($value === false || $value === '') throw new \RuntimeException('Configuration DEV manquante : ' . $name);
    define('WB_DEV_' . $name, $value);
}
$compression = getenv('WB_DEV_COMPRESSION') ?: 'tar';
if (!in_array($compression, ['tar', 'phpzip'], true)) throw new \RuntimeException('Compression DEV invalide');
define('WB_DEV_COMPRESSION', $compression);

class Config extends Config____Sample
{
    public const DEBUG = false;
    public const UI_PASSWORD = \WB_DEV_UI_PASSWORD;
    public const LOCAL_BACKUP_API_KEY = \WB_DEV_API_KEY;
    public const URL_TOKEN = \WB_DEV_URL_TOKEN;
    public const COMPRESSION_TYPE = \WB_DEV_COMPRESSION;
    public const LOCAL_BACKUP_RETENTION = null;
    public const S3_ENABLED = false;

    public static function localStorageBackupDir() { return '/data/backups'; }

    private static function database(): array {
        return ['type' => 'db', 'host' => 'mariadb', 'port' => 3306, 'user' => 'backup_dev',
            'pwd' => \WB_DEV_DB_PASSWORD, 'db_name' => 'website_backup_dev', 'file_name' => 'demo.sql',
            'settings' => ['routines' => false, 'add-drop-database' => false]];
    }

    public static function groups() {
        return [
            'demo_files' => ['prefix' => 'demo_files_', 'send_s3' => false, 'items' => [
                ['type' => 'dir', 'dir' => '/data/site', 'backup_dir' => 'site']]],
            'demo_database' => ['prefix' => 'demo_database_', 'send_s3' => false, 'items' => [self::database()]],
        ];
    }

    public static function downloadDBApi() { return ['demo' => self::database()]; }

    public static function tasks() {
        $common = ['folder_root' => '/data/site', 'generic_inventory_files' => '/data/integrity/site.json'];
        return ['demo_integrity' => [
            array_merge($common, ['name' => 'Créer l’inventaire', 'task' => 'integrity_build_inventory']),
            array_merge($common, ['name' => 'Vérifier les fichiers', 'task' => 'integrity_check', 'integrity_type' => 'generic']),
        ]];
    }
}
