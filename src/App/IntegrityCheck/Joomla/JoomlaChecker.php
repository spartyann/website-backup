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

		// Les extensions vérifiées en détail par JoomlaExtensionsChecker ne doivent pas aussi remonter comme
		// added_folder/missing_folder générique côté noyau (sinon la même extension apparaît deux fois : une fois
		// en bruit générique, une fois en détail précis). On calcule d'abord la liste des dossiers déjà couverts.
		$extResult = null;
		$covered = [];
		if ($task['check_extensions'] ?? false)
		{
			$extResult = JoomlaExtensionsChecker::check($task, $tmpDir);
			$covered = $extResult['covered_folders'];
		}

		$added = self::excludeCoveredPaths($diff['added'], $covered);
		$addedFolders = self::excludeCoveredPaths($diff['added_folders'], $covered);
		$updated = $diff['updated'];
		$missing = self::excludeCoveredPaths($diff['missing'], $covered);
		$missingFolders = self::excludeCoveredPaths($diff['missing_folders'], $covered);

		$result_strings = FileHashInventory::formatDiffStrings([
			'added' => $added, 'added_folders' => $addedFolders,
			'updated' => $updated,
			'missing' => $missing, 'missing_folders' => $missingFolders,
		]);
		array_unshift($result_strings, "Version Joomla détectée : $version");

		if ($extResult !== null)
		{
			$result_strings = array_merge($result_strings, $extResult['result_strings']);
			$added = array_merge($added, $extResult['added_files']);
			$addedFolders = array_merge($addedFolders, $extResult['added_folders']);
			$updated = array_merge($updated, $extResult['updated_files']);
			$missing = array_merge($missing, $extResult['missing_files']);
			$missingFolders = array_merge($missingFolders, $extResult['missing_folders']);
		}

		// Seuls les fichiers modifiés (hash différent) sont un signal fort de compromission.
		// added/missing sont attendus (configuration.php, uploads dans images/media, installation/ supprimé...) et se filtrent via ignored_files/ignored_folders si trop bruyants.
		$result = count($updated) === 0 ? 'OK' : 'KO';

		return [
			'result' => $result,
			'result_strings' => $result_strings,
			'added_files' => $added,
			'added_folders' => $addedFolders,
			'updated_files' => $updated,
			'missing_files' => $missing,
			'missing_folders' => $missingFolders,
			'database_items_found' => []
		];
	}

	// Retire de $paths tout chemin égal à, ou situé sous, l'un des dossiers de $coveredFolders
	private static function excludeCoveredPaths(array $paths, array $coveredFolders): array
	{
		if (count($coveredFolders) === 0) return $paths;

		return array_values(array_filter($paths, function($path) use ($coveredFolders) {
			foreach ($coveredFolders as $folder)
			{
				if ($path === $folder || str_starts_with($path, $folder . '/')) return false;
			}
			return true;
		}));
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
