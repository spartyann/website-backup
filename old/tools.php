<?php


function getFileDate($file){
	
	if (preg_match('/[0-9]+\-[0-9]+\-[0-9]+\_\_[0-9]+\-[0-9]+\-[0-9]+/', $file, $matches) == 1){
		$arr = explode("__", $matches[0]);
		$arr[1] = str_replace("-", ":", $arr[1]);
		return $arr[0]. " " . $arr[1];
	} else {
		
		return date("Y-m-d H:i:s", filemtime($file));
	}
}

function startsWith($haystack, $needle) {
     $length = strlen($needle);
     return (substr($haystack, 0, $length) === $needle);
}

function endsWith($haystack, $needle) {
    $length = strlen($needle);
    if ($length == 0) {
        return true;
    }

    return (substr($haystack, -$length) === $needle);
}

function cleanAndCompleteDirPath($dir){
	$dir = str_replace('\\', '/', $dir);
	if (endsWith($dir, "/") === false) $dir .= "/";
	return $dir;
}

function getBackupFiles($dir){
	$dir = cleanAndCompleteDirPath($dir);
	
	$res = array();
	$files = scandir($dir);

	foreach($files as $file){
		if ($file == '.' || $file == '..' 
			|| endsWith($file, ".php")
			|| endsWith($file, ".config")
			|| strtolower($file) == ".htaccess") continue;
		
		$res[] = $dir . $file;
	}
	
	return $res;
}

function getDiffInSeconds(\DateTime $dateTime){
	$now = new DateTime();
	$interval = $now->diff($dateTime);
	return $interval->days * 3600 * 24 + $interval->h * 3600 + $interval->i * 60 + $interval->s;
}

function deleteOldBackup($dir, $seconds){
	$files = getBackupFiles($dir);
	$result = array();
	
	foreach($files as $file){
		$totalSec = getDiffInSeconds(new DateTime(getFileDate($file)));
		
		// XX secondes max
		if ($totalSec >= $seconds) {
			unlink($file);
		}
	}

}

function MakeZip($zipFile, $files, $dirs){
	
	// Initialize archive object
	
	$zip = new ZipArchive();
	$zip->open($zipFile, ZipArchive::CREATE);


	foreach ($files as $file) {
		$zip->addFile($file, basename($file));
	}

	foreach ($dirs as $dir) {
		
		$dir = realpath($dir);
		$dir = cleanAndCompleteDirPath($dir);
		
		$dir_name = basename($dir);
		$dir_dir = dirname($dir);
		
		$zip->addEmptyDir($dir_name);
		
		$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::SELF_FIRST);
		
		foreach ($files as $file)
        {
			$file = str_replace('\\', '/', $file);
			
            // Ignore "." and ".." folders
            if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) )
                continue;
			
			$localName = str_replace($dir_dir . '/', '', $file);
			
			if (is_dir($file) === true){
                $zip->addEmptyDir($localName);
				
            } else if (is_file($file) === true) {
				$zip->addFile($file, $localName);
				
            }
			
		}
	}
	// Zip archive will be created only after closing object
	$zip->close();

}

// Télécharge le fichier $url vers $file
function downloadFile($url, $file) {

	$fpRead  = @fopen($url, "rb");
	if ($fpRead === false) { return; }
	
	$fpWrite = @fopen($file, "w+b");
	if ($fpWrite === false) { fclose($fpRead); return; }

	while (!feof($fpRead)) {
		fwrite($fpWrite, fread($fpRead, 8192));
	}
	 
	fclose($fpWrite);
	fclose($fpRead);
}

