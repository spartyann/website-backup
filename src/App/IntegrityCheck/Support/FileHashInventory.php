<?php

namespace App\IntegrityCheck\Support;

use App\Tools\FileTools;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class FileHashInventory
{

	public static function build(string $rootDir, array $ignoredFiles, array $ignoredFolders): array
	{
		$root = FileTools::cleanAndCompleteDirPath(realpath($rootDir) ?: $rootDir);

		$files = [];

		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ($iterator as $fileInfo)
		{
			if ($fileInfo->isDir()) continue;

			$fullPath = str_replace('\\', '/', $fileInfo->getPathname());
			$relPath = substr($fullPath, strlen($root));

			if (self::isIgnored($relPath, $ignoredFiles, $ignoredFolders)) continue;

			$files[$relPath] = hash_file('sha256', $fullPath);
		}

		return [
			'built_at' => date('c'),
			'algo' => 'sha256',
			'root' => $root,
			'files' => $files,
		];
	}

	// Hash uniquement les chemins fournis (relatifs à $rootDir), sans scanner le reste de l'arborescence.
	// Utile quand on connaît déjà la liste exacte des fichiers attendus (ex: mapping issu du manifeste d'une extension).
	public static function buildForPaths(string $rootDir, array $relPaths): array
	{
		$root = FileTools::cleanAndCompleteDirPath(realpath($rootDir) ?: $rootDir);

		$files = [];
		foreach ($relPaths as $relPath)
		{
			$fullPath = $root . ltrim($relPath, '/');
			if (is_file($fullPath) == false) continue;

			$files[$relPath] = hash_file('sha256', $fullPath);
		}

		return [
			'built_at' => date('c'),
			'algo' => 'sha256',
			'root' => $root,
			'files' => $files,
		];
	}

	private static function isIgnored(string $relPath, array $ignoredFiles, array $ignoredFolders): bool
	{
		foreach ($ignoredFiles as $file)
		{
			$file = ltrim(str_replace('\\', '/', $file), '/');
			if ($relPath === $file) return true;
		}

		foreach ($ignoredFolders as $folder)
		{
			$folder = trim(str_replace('\\', '/', $folder), '/');
			if ($folder === '') continue;
			if ($relPath === $folder || str_starts_with($relPath, $folder . '/')) return true;
		}

		return false;
	}

	private static function filterIgnored(array $filesMap, array $ignoredFiles, array $ignoredFolders): array
	{
		$result = [];
		foreach ($filesMap as $path => $hash)
		{
			if (self::isIgnored($path, $ignoredFiles, $ignoredFolders)) continue;
			$result[$path] = $hash;
		}
		return $result;
	}

	public static function save(string $jsonPath, array $inventory): void
	{
		$dir = dirname($jsonPath);
		if (is_dir($dir) == false) mkdir($dir, 0777, true);

		file_put_contents($jsonPath, json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
	}

	public static function load(string $jsonPath): ?array
	{
		if (is_file($jsonPath) == false) return null;

		$content = file_get_contents($jsonPath);
		if ($content === false) return null;

		$data = json_decode($content, true);
		if (is_array($data) == false || isset($data['files']) == false) return null;

		return $data;
	}

	// Compare deux maps [relPath => hash] et retourne les fichiers ajoutés/modifiés/manquants entre $baselineFiles et $currentFiles.
	// $ignoredFiles/$ignoredFolders sont ré-appliqués ici des deux côtés (baseline ET current) : la référence (inventaire generic
	// construit avant l'ajout d'une règle d'ignore, ou archive Joomla pristine qui ignore les ignored_folders du site) peut
	// contenir des chemins que le scan courant exclut déjà, sinon ils remontent à tort en "missing".
	// Les fichiers ajoutés/manquants appartenant à un dossier entièrement nouveau/disparu (>= $minFilesPerFolder fichiers) sont
	// regroupés dans added_folders/missing_folders plutôt que listés individuellement.
	public static function diff(array $baselineFiles, array $currentFiles, int $minFilesPerFolder = 2, array $ignoredFiles = [], array $ignoredFolders = []): array
	{
		if (count($ignoredFiles) > 0 || count($ignoredFolders) > 0)
		{
			$baselineFiles = self::filterIgnored($baselineFiles, $ignoredFiles, $ignoredFolders);
			$currentFiles = self::filterIgnored($currentFiles, $ignoredFiles, $ignoredFolders);
		}

		$added = [];
		$updated = [];
		$missing = [];

		foreach ($currentFiles as $path => $hash)
		{
			if (array_key_exists($path, $baselineFiles) == false)
			{
				$added[] = $path;
			}
			else if ($baselineFiles[$path] !== $hash)
			{
				$updated[] = $path;
			}
		}

		foreach ($baselineFiles as $path => $hash)
		{
			if (array_key_exists($path, $currentFiles) == false)
			{
				$missing[] = $path;
			}
		}

		sort($updated);

		$addedGrouped = self::groupByFolder($added, self::buildFolderSet($baselineFiles), $minFilesPerFolder);
		$missingGrouped = self::groupByFolder($missing, self::buildFolderSet($currentFiles), $minFilesPerFolder);

		return [
			'added' => $addedGrouped['files'],
			'added_folders' => $addedGrouped['folders'],
			'updated' => $updated,
			'missing' => $missingGrouped['files'],
			'missing_folders' => $missingGrouped['folders'],
		];
	}

	// Retourne l'ensemble de tous les dossiers ancêtres (à toute profondeur) des fichiers de $filesMap
	private static function buildFolderSet(array $filesMap): array
	{
		$set = [];

		foreach (array_keys($filesMap) as $path)
		{
			$segments = explode('/', $path);
			array_pop($segments); // retire le nom de fichier

			$current = '';
			foreach ($segments as $seg)
			{
				$current = $current === '' ? $seg : $current . '/' . $seg;
				$set[$current] = true;
			}
		}

		return $set;
	}

	// Pour chaque fichier de $files, remonte jusqu'au dossier ancêtre le plus haut absent de $referenceFolderSet.
	// Les fichiers dont ce dossier regroupe au moins $minFilesPerFolder fichiers sont absorbés dans "folders" ; le reste reste dans "files".
	private static function groupByFolder(array $files, array $referenceFolderSet, int $minFilesPerFolder): array
	{
		$fileTopFolder = [];

		foreach ($files as $file)
		{
			$segments = explode('/', $file);
			array_pop($segments);

			$path = '';
			$topNewFolder = null;

			foreach ($segments as $seg)
			{
				$path = $path === '' ? $seg : $path . '/' . $seg;
				if (isset($referenceFolderSet[$path]) == false)
				{
					$topNewFolder = $path;
					break;
				}
			}

			$fileTopFolder[$file] = $topNewFolder;
		}

		$counts = [];
		foreach ($fileTopFolder as $folder)
		{
			if ($folder !== null) $counts[$folder] = ($counts[$folder] ?? 0) + 1;
		}

		$folders = [];
		foreach ($counts as $folder => $count)
		{
			if ($count >= $minFilesPerFolder) $folders[] = $folder;
		}

		$remainingFiles = [];
		foreach ($fileTopFolder as $file => $folder)
		{
			if ($folder !== null && in_array($folder, $folders)) continue; // absorbé dans un added_folder/missing_folder

			$remainingFiles[] = $file;
		}

		sort($folders);
		sort($remainingFiles);

		return [ 'files' => $remainingFiles, 'folders' => $folders ];
	}

	public static function formatDiffStrings(array $diff): array
	{
		$lines = [];

		if (count($diff['added_folders'] ?? []) > 0)
		{
			$lines[] = "New folders:";
			foreach ($diff['added_folders'] as $f) $lines[] = "- $f/";
		}

		if (count($diff['added']) > 0)
		{
			$lines[] = "New files:";
			foreach ($diff['added'] as $f) $lines[] = "- $f";
		}

		if (count($diff['updated']) > 0)
		{
			$lines[] = "Modified files:";
			foreach ($diff['updated'] as $f) $lines[] = "- $f";
		}

		if (count($diff['missing_folders'] ?? []) > 0)
		{
			$lines[] = "Missing folders:";
			foreach ($diff['missing_folders'] as $f) $lines[] = "- $f/";
		}

		if (count($diff['missing']) > 0)
		{
			$lines[] = "Missing files:";
			foreach ($diff['missing'] as $f) $lines[] = "- $f";
		}

		return $lines;
	}

}
