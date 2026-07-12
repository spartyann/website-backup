<?php

namespace App\IntegrityCheck\Joomla;

use App\IntegrityCheck\Support\Downloader;
use App\Tools\FileTools;
use App\Tools\PrintTools;
use PDO;
use ZipArchive;

// Résout puis met en cache (par élément+version) l'inventaire hashé "pristine" d'une extension Joomla,
// en essayant dans l'ordre : archive manuelle déposée par l'utilisateur, puis résolution automatique
// via les Update Sites de Joomla. Retourne null si aucune référence n'a pu être obtenue (extension non
// bloquante : simplement ignorée par l'appelant).
class ExtensionReference
{

	public static function getInventory(PDO $pdo, array $task, array $extension, string $tmpDir): ?array
	{
		$element = $extension['element'];
		$version = $extension['version'];
		if ($version === null) return null;

		$cacheFolder = rtrim(str_replace('\\', '/', $task['joomla_plg_inventory_folder']), '/') . '/extensions';
		$cacheFile = $cacheFolder . '/' . $element . '_' . str_replace('.', '_', $version) . '.json';

		$cached = \App\IntegrityCheck\Support\FileHashInventory::load($cacheFile);
		if ($cached !== null) return $cached;

		$zipFile = self::resolveZip($pdo, $task, $extension, $tmpDir);
		if ($zipFile === null) return null;

		$extractDir = $tmpDir . '/ext_' . $element . '_' . str_replace('.', '_', $version) . '_extracted';

		try
		{
			if (is_dir($extractDir) == false) mkdir($extractDir, 0777, true);

			$zip = new ZipArchive();
			if ($zip->open($zipFile) !== true) return null;
			$zip->extractTo($extractDir);
			$zip->close();

			$manifestFile = ExtensionManifestParser::findManifestFile($extractDir);
			if ($manifestFile === null)
			{
				PrintTools::text("Extension $element : manifeste introuvable dans l'archive, ignorée.");
				return null;
			}

			$map = ExtensionManifestParser::parse($manifestFile, $extension['type'], $element, $extension['folder'], $extension['client_id']);
			$map = ExtensionManifestParser::expandDirectoryMappings($map, $extractDir);

			$files = [];
			foreach ($map as $sitePath => $zipRelPath)
			{
				$full = $extractDir . '/' . $zipRelPath;
				if (is_file($full)) $files[$sitePath] = hash_file('sha256', $full);
			}

			$inventory = [
				'built_at' => date('c'),
				'algo' => 'sha256',
				'element' => $element,
				'version' => $version,
				'files' => $files,
			];

			\App\IntegrityCheck\Support\FileHashInventory::save($cacheFile, $inventory);

			return $inventory;
		}
		finally
		{
			if (is_file($zipFile)) unlink($zipFile);
			if (is_dir($extractDir)) FileTools::removeDir($extractDir);
		}
	}

	private static function resolveZip(PDO $pdo, array $task, array $extension, string $tmpDir): ?string
	{
		$element = $extension['element'];

		// 1) Archive manuelle fournie par l'utilisateur (prioritaire, toujours fiable)
		$manualFolder = $task['extensions_manual_archives_folder'] ?? null;
		if ($manualFolder !== null)
		{
			$manualZip = rtrim(str_replace('\\', '/', $manualFolder), '/') . '/' . $element . '.zip';
			if (is_file($manualZip))
			{
				PrintTools::text("Extension $element : archive manuelle trouvée.");

				$copy = $tmpDir . '/manual_' . $element . '.zip';
				copy($manualZip, $copy);
				return $copy;
			}
		}

		// 2) Résolution automatique via Update Site
		try
		{
			$url = JoomlaUpdateSiteResolver::resolveDownloadUrl(
				$pdo,
				$task['db_table_prefix'],
				$extension['extension_id'],
				$element,
				$extension['version'],
				$task['extensions_extra_query_overrides'] ?? [],
				$tmpDir
			);

			if ($url === null) return null;

			PrintTools::text("Extension $element : téléchargement de la référence officielle (version {$extension['version']})...");

			$zipFile = $tmpDir . '/auto_' . $element . '.zip';
			Downloader::downloadToFile($url, $zipFile, 60);

			return $zipFile;
		}
		catch (\Throwable $e)
		{
			PrintTools::text("Extension $element : référence indisponible (" . $e->getMessage() . ").");
			return null;
		}
	}

}
