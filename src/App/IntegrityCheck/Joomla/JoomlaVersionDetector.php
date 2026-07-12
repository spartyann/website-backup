<?php

namespace App\IntegrityCheck\Joomla;

use Exception;

class JoomlaVersionDetector
{

	// Détecte la version Joomla en lisant les fichiers de version en texte brut (jamais d'include/eval sur du code potentiellement compromis)
	public static function detect(string $folderRoot): string
	{
		$root = rtrim(str_replace('\\', '/', $folderRoot), '/');

		$candidates = [
			$root . '/libraries/src/Version.php',       // Joomla 4 / 5
			$root . '/libraries/cms/version/version.php', // Joomla 3
		];

		foreach ($candidates as $file)
		{
			if (is_file($file) == false) continue;

			$version = self::parseConstants($file);
			if ($version !== null) return $version;
		}

		throw new Exception("Impossible de détecter la version Joomla dans $folderRoot (fichiers de version non trouvés ou format non reconnu).");
	}

	private static function parseConstants(string $file): ?string
	{
		$content = file_get_contents($file);
		if ($content === false) return null;

		if (preg_match('/const\s+MAJOR_VERSION\s*=\s*(\d+)/', $content, $mMajor) !== 1) return null;
		if (preg_match('/const\s+MINOR_VERSION\s*=\s*(\d+)/', $content, $mMinor) !== 1) return null;
		if (preg_match('/const\s+PATCH_VERSION\s*=\s*(\d+)/', $content, $mPatch) !== 1) return null;

		return $mMajor[1] . '.' . $mMinor[1] . '.' . $mPatch[1];
	}

}
