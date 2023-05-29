<?php

namespace App\Tools;

use Config\Config;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class FileTools 
{
    public static function getReadableSize($bytes, $decimals = 2){
		$size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
		$factor = floor((strlen($bytes) - 1) / 3);
		return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$size[$factor];
	}

	public static function cleanAndCompleteDirPath($dir){
		$dir = str_replace('\\', '/', $dir);
		if (str_ends_with($dir, "/") === false) $dir .= "/";
		return $dir;
	}


	public static function prepareTempDir()
	{
		$dir = Config::localStorageBackupDir();
		if (is_dir($dir) == false) mkdir($dir);
		
		$tmp = $dir . '/temp';
		if (is_dir($dir)) shell_exec("rm -rf " . escapeshellarg($tmp)); // Delete Tmp dir

		// Create Tmp dir
		mkdir($tmp);

		return $tmp;
	}

		
	public static function makePhpZip($zipFile, $files, $staticDirs)
	{
		// Initialize archive object
		$zip = new ZipArchive();
		$zip->open($zipFile, ZipArchive::CREATE);

		//dd($files, $dirs);
		foreach ($files as $file)
		{
			if (is_file($file) == false) throw new Exception("File does not exists: $file");
			$zip->addFile($file, basename($file));
		}

		foreach ($staticDirs as $item)
		{
			$backupDirName = $item['backup_dir'];
			$dir = $item['dir'];

			if (is_dir($dir) == false) throw new Exception("Directory does not exists: $dir");
			
			$dir = realpath($dir);
			$dir = self::cleanAndCompleteDirPath($dir);
			
			//$dir_name = basename($dir);
			$dir_dir = dirname($dir);
			
			$zip->addEmptyDir($backupDirName);
			
			$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), RecursiveIteratorIterator::SELF_FIRST);
			
			foreach ($files as $file)
			{
				$file = str_replace('\\', '/', $file);
				
				// Ignore "." and ".." folders
				if( in_array(substr($file, strrpos($file, '/')+1), array('.', '..')) )
					continue;
				
				$localName = str_replace($dir_dir . '/', $backupDirName . '/', $file);
				
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



	public static function tar($tarFile, $files, $staticDirs)
	{
		foreach($files as $file)
		{
			if (is_file($file) == false) throw new Exception("File not found: $file");

			$name = basename($file);
			$dir = dirname($file);
			$cmd = "cd " . escapeshellarg($dir) . " && tar --preserve-permissions -rf " .  escapeshellarg($tarFile) . " " . escapeshellarg($name);
			
			CommandTools::exec($cmd, "Error on tar: ", $output);
		}
		
		
		foreach($staticDirs as $item)
		{
			$backupDirName = $item['backup_dir'];
			$dir = $item['dir'];

			if (is_dir($dir) == false) throw new Exception("Directory not found: $dir");
			
			$name = basename($dir);
			$parentDir = dirname($dir);

			$transform = "--transform=" . escapeshellarg("s,^" . preg_quote($name, ',') . "," . preg_quote($backupDirName . '/' . $name, ',') . ",");
			
			$cmd = "cd " . escapeshellarg($parentDir) . " && tar --preserve-permissions -rf " .  escapeshellarg($tarFile) . " " . $transform . " " . escapeshellarg($name);

			CommandTools::exec($cmd, "Error on tar: ", $output);
		}

		// tar --preserve-permissions -cf "$BAK_FILE.tmp" *
		// tar --preserve-permissions -rf "$BAK_FILE.tmp" ged_files/*
		
		// untar
		// tar --preserve-permissions -xf "$BAK_FILE"
	}



}
