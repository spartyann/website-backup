<?php

namespace App\IntegrityCheck\Joomla;

use App\IntegrityCheck\Support\DbConnector;
use App\IntegrityCheck\Support\FileHashInventory;
use PDO;

// Vérifie les fichiers de chaque extension tierce installée (plugins/modules/composants/templates/librairies)
// par rapport à une archive officielle résolue automatiquement ou fournie manuellement. Complète le check du
// noyau Joomla (JoomlaChecker), qui lui ne couvre que les fichiers embarqués dans le Full Package officiel.
class JoomlaExtensionsChecker
{

	public static function check(array $task, string $tmpDir): array
	{
		$pdo = DbConnector::connect($task);
		$prefix = $task['db_table_prefix'];

		$enabledTypes = self::enabledTypes($task['check_extension_types'] ?? []);
		$ignoreList = $task['extensions_ignore'] ?? [];

		$extensions = self::getThirdPartyExtensions($pdo, $prefix, $enabledTypes, $ignoreList);

		$added = [];
		$addedFolders = [];
		$updated = [];
		$missing = [];
		$missingFolders = [];
		$skipped = [];
		$checkedCount = 0;
		$coveredFolders = [];

		foreach ($extensions as $extension)
		{
			$reference = ExtensionReference::getInventory($pdo, $task, $extension, $tmpDir);

			if ($reference === null)
			{
				$skipped[] = $extension['element'];
				continue;
			}

			$checkedCount++;

			// Cette extension est vérifiée en détail ci-dessous : ses dossiers ne doivent pas remonter en plus
			// comme added_folder générique dans le diff du noyau (JoomlaChecker s'en sert pour les exclure).
			$basePaths = ExtensionManifestParser::basePaths($extension['type'], $extension['element'], $extension['folder']);
			$coveredFolders = array_merge($coveredFolders, $basePaths);

			$prefix2 = "[{$extension['element']}] ";

			// Le regroupement par dossier est fait séparément pour chaque racine de l'extension (site/admin/media) :
			// en diffant sur l'ensemble des chemins complets, le "dossier le plus haut absent" pouvait remonter au-dessus
			// de la racine de l'extension (ex: tout "plugins/" ou "language/" signalé manquant si un seul petit plugin
			// n'a aucun fichier présent), ce qui est trompeur. En bornant la recherche à la racine de l'extension,
			// le regroupement reste toujours à l'intérieur de son propre périmètre.
			foreach ($basePaths as $base)
			{
				$refSubset = self::subsetUnderPath($reference['files'], $base);

				// Vrai scan récursif du dossier (et non un simple lookup des chemins connus du manifeste) :
				// c'est le seul moyen de détecter un fichier ajouté qui n'existe pas dans la référence officielle
				// (ex: un fichier suspect déposé dans le dossier d'une extension par ailleurs légitime).
				$baseDir = rtrim(str_replace('\\', '/', $task['folder_root']), '/') . '/' . $base;
				$curSubset = is_dir($baseDir) ? FileHashInventory::build($baseDir, [], [])['files'] : [];

				if (count($refSubset) === 0 && count($curSubset) === 0) continue;

				$diff = FileHashInventory::diff($refSubset, $curSubset, $task['folder_group_min_files'] ?? 2);

				foreach ($diff['added'] as $f) $added[] = $prefix2 . $base . '/' . $f;
				foreach ($diff['added_folders'] as $f) $addedFolders[] = $prefix2 . $base . '/' . $f;
				foreach ($diff['updated'] as $f) $updated[] = $prefix2 . $base . '/' . $f;
				foreach ($diff['missing'] as $f) $missing[] = $prefix2 . $base . '/' . $f;
				foreach ($diff['missing_folders'] as $f) $missingFolders[] = $prefix2 . $base . '/' . $f;
			}
		}

		$result_strings = [];
		$result_strings[] = "Extensions vérifiées : $checkedCount / " . count($extensions) . " (" . count($skipped) . " sans référence disponible)";

		if (count($updated) > 0)
		{
			$result_strings[] = "Fichiers d'extensions modifiés :";
			foreach ($updated as $f) $result_strings[] = "- $f";
		}

		if (count($missingFolders) > 0)
		{
			$result_strings[] = "Dossiers d'extensions manquants :";
			foreach ($missingFolders as $f) $result_strings[] = "- $f/";
		}

		if (count($missing) > 0)
		{
			$result_strings[] = "Fichiers d'extensions manquants :";
			foreach ($missing as $f) $result_strings[] = "- $f";
		}

		if (count($addedFolders) > 0)
		{
			$result_strings[] = "Dossiers d'extensions ajoutés :";
			foreach ($addedFolders as $f) $result_strings[] = "- $f/";
		}

		if (count($added) > 0)
		{
			$result_strings[] = "Fichiers d'extensions ajoutés :";
			foreach ($added as $f) $result_strings[] = "- $f";
		}

		return [
			'result_strings' => $result_strings,
			'added_files' => $added,
			'added_folders' => $addedFolders,
			'updated_files' => $updated,
			'missing_files' => $missing,
			'missing_folders' => $missingFolders,
			'skipped_extensions' => $skipped,
			'covered_folders' => $coveredFolders,
		];
	}

	// Extrait les entrées de $filesMap situées sous $basePath, ré-indexées en chemins relatifs à $basePath
	private static function subsetUnderPath(array $filesMap, string $basePath): array
	{
		$subset = [];
		foreach ($filesMap as $path => $hash)
		{
			if ($path === $basePath) continue; // pas de fichier concevable exactement sur la racine
			if (str_starts_with($path, $basePath . '/') == false) continue;

			$subset[substr($path, strlen($basePath) + 1)] = $hash;
		}
		return $subset;
	}

	private static function enabledTypes(array $checkExtensionTypes): array
	{
		$defaults = [ 'component' => true, 'module' => true, 'plugin' => true, 'template' => true, 'library' => true ];
		$config = array_merge($defaults, $checkExtensionTypes);

		return array_keys(array_filter($config));
	}

	private static function getThirdPartyExtensions(PDO $pdo, string $prefix, array $enabledTypes, array $ignoreList): array
	{
		if (count($enabledTypes) === 0) return [];

		$placeholders = implode(',', array_fill(0, count($enabledTypes), '?'));

		$stmt = $pdo->prepare(
			"SELECT extension_id, name, element, type, folder, client_id, enabled, manifest_cache
			 FROM `{$prefix}extensions`
			 WHERE protected = 0 AND type IN ($placeholders)"
		);
		$stmt->execute($enabledTypes);

		$extensions = [];
		foreach ($stmt->fetchAll() as $row)
		{
			if (in_array($row['element'], $ignoreList)) continue;

			$version = null;
			$manifest = json_decode($row['manifest_cache'] ?? '', true);
			if (is_array($manifest) && isset($manifest['version']) && preg_match('/^\d+(\.\d+)*/', $manifest['version']))
			{
				$version = $manifest['version'];
			}

			$extensions[] = [
				'extension_id' => (int)$row['extension_id'],
				'name' => $row['name'],
				'element' => $row['element'],
				'type' => $row['type'],
				'folder' => $row['folder'] ?? '',
				'client_id' => (int)$row['client_id'],
				'enabled' => (bool)$row['enabled'],
				'version' => $version,
			];
		}

		return $extensions;
	}

}
