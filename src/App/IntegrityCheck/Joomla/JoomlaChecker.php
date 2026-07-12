<?php

namespace App\IntegrityCheck\Joomla;

use App\IntegrityCheck\Support\FileHashInventory;
use App\Tools\PrintTools;
use Exception;

class JoomlaChecker
{

	public static function check(array $task, string $tmpDir): array
	{
		try
		{
			$version = JoomlaVersionDetector::detect($task['folder_root']);
			PrintTools::text("Detected Joomla version: $version");
		}
		catch (Exception $e)
		{
			return self::errorResult("Détection de version Joomla échouée: " . $e->getMessage());
		}

		try
		{
			$pristine = JoomlaCoreReference::getInventory($version, $task['joomla_plg_inventory_folder'], $tmpDir);
		}
		catch (Exception $e)
		{
			return self::errorResult("Récupération de la référence Joomla $version échouée: " . $e->getMessage());
		}

		$current = FileHashInventory::build(
			$task['folder_root'],
			$task['ignored_files'] ?? [],
			$task['ignored_folders'] ?? []
		);

		$diff = FileHashInventory::diff(
			$pristine['files'],
			$current['files'],
			$task['folder_group_min_files'] ?? 2,
			$task['ignored_files'] ?? [],
			$task['ignored_folders'] ?? []
		);

		$result_strings = FileHashInventory::formatDiffStrings($diff);
		array_unshift($result_strings, "Version Joomla détectée : $version");

		// Seuls les fichiers modifiés (hash différent) sont un signal fort de compromission.
		// added/missing sont attendus (configuration.php, uploads dans images/media, installation/ supprimé...) et se filtrent via ignored_files/ignored_folders si trop bruyants.
		$result = count($diff['updated']) === 0 ? 'OK' : 'KO';

		return [
			'result' => $result,
			'result_strings' => $result_strings,
			'added_files' => $diff['added'],
			'added_folders' => $diff['added_folders'],
			'updated_files' => $diff['updated'],
			'missing_files' => $diff['missing'],
			'missing_folders' => $diff['missing_folders'],
			'database_items_found' => []
		];
	}

	private static function errorResult(string $message): array
	{
		return [
			'result' => 'KO',
			'result_strings' => [ $message ],
			'added_files' => [],
			'added_folders' => [],
			'updated_files' => [],
			'missing_files' => [],
			'missing_folders' => [],
			'database_items_found' => []
		];
	}

}
