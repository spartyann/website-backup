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

	// Compare deux maps [relPath => hash] et retourne les fichiers ajoutés/modifiés/manquants entre $baselineFiles et $currentFiles
	public static function diff(array $baselineFiles, array $currentFiles): array
	{
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

		sort($added);
		sort($updated);
		sort($missing);

		return [ 'added' => $added, 'updated' => $updated, 'missing' => $missing ];
	}

	public static function formatDiffStrings(array $diff): array
	{
		$lines = [];

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

		if (count($diff['missing']) > 0)
		{
			$lines[] = "Missing files:";
			foreach ($diff['missing'] as $f) $lines[] = "- $f";
		}

		return $lines;
	}

}
