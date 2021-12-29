<?php

require_once("../tools.php");

$result = array();
$files = getBackupFiles(__DIR__);

foreach($files as $file){

	$object = array();
	$object['name'] = basename($file);
	$object['size'] = filesize($file);
	$object['date'] = getFileDate($file);
	
	$result[] = $object;
}

echo serialize($result);
