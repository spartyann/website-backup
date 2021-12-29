<?php

set_time_limit(60 * 45); // 45 minutes

require_once("conf.php");

if (isset($_SERVER['HTTPS']) == false || $_SERVER['HTTPS'] != 'on') {
    echo "ERROR no HTTPS";
	exit;
}

if (isset($_GET["security_code"]) == false || $_GET["security_code"] != $securityCode) {
	echo "ERROR";
	exit;
}

$include_dirs=false;

if (isset($_GET["include_dirs"]) && $_GET["include_dirs"] == "true") {
	$include_dirs=true;
}

require_once("mysqldump.php");
require_once("tools.php");

try {
	$tempDir = __DIR__ . "/$export_dir/";
	//if (is_dir($tempDir) == false) mkdir($tempDir);

	deleteOldBackup($tempDir, 0);
	
	
	$dumpFile = $tempDir . "dump.sql";
	if (is_file($dumpFile)) unlink($dumpFile);

	$zipFile = $tempDir . $backupFilePrefix . date("Y-m-d__H-i-s") . ".zip";
	if (is_file($zipFile)) unlink($zipFile);

    $dump = new Ifsnop\Mysqldump\Mysqldump("mysql:host=$mysql_host;dbname=$mysql_dbname", $mysql_username, $mysql_pwd);
    $dump->start($dumpFile);

	MakeZip($zipFile, array($dumpFile), $include_dirs == true ? $dirs_to_zip : array());
	
	unlink($dumpFile);
	
	echo "OK-$export_dir";
	
} catch (\Exception $e) {
    echo 'ERROR-' . $e->getMessage();
}
