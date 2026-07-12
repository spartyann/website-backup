<?php

namespace App\IntegrityCheck\Joomla;

use App\IntegrityCheck\Support\Downloader;
use App\IntegrityCheck\Support\FileHashInventory;
use App\Tools\FileTools;
use App\Tools\PrintTools;
use Exception;
use ZipArchive;

class JoomlaCoreReference
{

	// Retourne l'inventaire hashé (pristine) du noyau Joomla pour une version donnée.
	// Mis en cache par version dans $cacheFolder (ex: joomla_5_1_2.json) pour éviter de re-télécharger/re-hasher à chaque check.
	public static function getInventory(string $version, string $cacheFolder, string $tmpDir): array
	{
		if (is_dir($cacheFolder) == false) mkdir($cacheFolder, 0777, true);

		$cacheFile = rtrim(str_replace('\\', '/', $cacheFolder), '/') . '/joomla_' . str_replace('.', '_', $version) . '.json';

		$existing = FileHashInventory::load($cacheFile);
		if ($existing !== null)
		{
			PrintTools::text("Joomla $version reference inventory found in cache: $cacheFile");
			return $existing;
		}

		PrintTools::text("Joomla $version reference inventory not cached, downloading official package...");

		$suffix = str_replace('.', '_', $version);
		$zipFile = rtrim($tmpDir, '/\\') . '/joomla_core_' . $suffix . '.zip';
		$extractDir = rtrim($tmpDir, '/\\') . '/joomla_core_' . $suffix . '_extracted';

		try
		{
			$zipUrl = "https://github.com/joomla/joomla-cms/releases/download/$version/Joomla_{$version}-Stable-Full_Package.zip";
			Downloader::downloadToFile($zipUrl, $zipFile);

			if (is_dir($extractDir) == false) mkdir($extractDir, 0777, true);

			$zip = new ZipArchive();
			if ($zip->open($zipFile) !== true)
			{
				throw new Exception("Impossible d'ouvrir l'archive Joomla téléchargée: $zipFile");
			}
			$zip->extractTo($extractDir);
			$zip->close();

			// "installation/" est supprimé sur un site en prod correctement durci : on l'exclut de la référence pour éviter un flot de faux positifs "missing"
			$inventory = FileHashInventory::build($extractDir, [], ['installation']);
			FileHashInventory::save($cacheFile, $inventory);

			PrintTools::text("Joomla $version reference inventory built and cached: $cacheFile");

			return $inventory;
		}
		catch (Exception $e)
		{
			throw new Exception("Impossible d'obtenir l'archive Joomla officielle pour la version $version: " . $e->getMessage(), 0, $e);
		}
		finally
		{
			if (is_file($zipFile)) unlink($zipFile);
			if (is_dir($extractDir)) FileTools::removeDir($extractDir);
		}
	}

}
