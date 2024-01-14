<?php

namespace App\Tools;

use Config\Config;
use Exception;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ZipArchive;

class FileTools 
{
	
	public static function getAllSubDirectories($dir)
	{
		if (is_dir($dir) == false) return [];
		$subDirs = glob($dir . '/*', GLOB_ONLYDIR);

		$res = [];
		foreach ($subDirs as $subDir)
		{
			$res[] = $subDir;
			$res = array_merge($res, self::getAllSubDirectories($subDir));
		}

		return $res;
	}
	
	public static function cleanupFileChars($string)
	{
		$string = str_replace(' ', '-', $string);
		$string = preg_replace('/[^A-Za-z0-9\-\_]/', '', $string);
		$string = preg_replace('/-+/', '-', $string);
		return $string;
	}

	public static function removeDir($dir)
	{
		if (is_dir($dir))
		{
			if (IS_WIN == false) shell_exec("rm -rf " . escapeshellarg($dir)); // Delete Tmp dir
			if (IS_WIN) shell_exec("rmdir /s /q " . escapeshellarg($dir)); // Delete Tmp dir
		}
	}

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

		self::removeDir($tmp);

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
			$ignoreExtensions = $item['ignore_extensions'] ?? null;
			if ($ignoreExtensions != null) $ignoreExtensions = array_map(function($s) { return strtolower($s);}, $ignoreExtensions);

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

				if ($ignoreExtensions != null)
				{
					$ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
					if (in_array($ext, $ignoreExtensions)) continue;
				}
				
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
			
			$exclude = '';
			if (isset($item['tar_exclude']) && empty($item['tar_exclude']) == false)
			{
				foreach(is_array($item['tar_exclude']) ? $item['tar_exclude'] : [ $item['tar_exclude'] ] as $exc)
				{
					$exclude .= ' --exclude="' . preg_quote($exc) . '"';
				}
			}

			foreach ($item['ignore_extensions'] ?? [] as $ext)
			{
				$exclude .= ' --exclude="*.' . preg_quote($ext) . '"';
			}

			$cmd = "cd " . escapeshellarg($parentDir) . " && tar --preserve-permissions $exclude -rf " .  escapeshellarg($tarFile) . " " . $transform . " " . escapeshellarg($name);

			CommandTools::exec($cmd, "Error on tar: ", $output);
		}

		// tar --preserve-permissions -cf "$BAK_FILE.tmp" *
		// tar --preserve-permissions -rf "$BAK_FILE.tmp" ged_files/*
		
		// untar
		// tar --preserve-permissions -xf "$BAK_FILE"
	}



}
