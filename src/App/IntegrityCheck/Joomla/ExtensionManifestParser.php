<?php

namespace App\IntegrityCheck\Joomla;

use Exception;
use SimpleXMLElement;

// Parse le manifeste XML d'une extension Joomla (installer manifest) pour déterminer, pour chaque fichier
// contenu dans l'archive, à quel chemin relatif du site il correspond une fois installé.
// L'installeur Joomla redistribue les fichiers de l'archive vers plusieurs emplacements du site selon le
// type d'extension (component/module/plugin/template/library) et les blocs <files>/<media>/<languages>
// du manifeste — contrairement au noyau Joomla où l'archive a déjà la même arborescence que le site.
class ExtensionManifestParser
{

	// Trouve le fichier manifeste XML à la racine d'une archive extraite (l'installeur Joomla n'impose pas de nom de fichier fixe)
	public static function findManifestFile(string $extractedDir): ?string
	{
		foreach (glob(rtrim($extractedDir, '/\\') . '/*.xml') ?: [] as $file)
		{
			$content = @file_get_contents($file, false, null, 0, 4096);
			if ($content !== false && preg_match('/<extension\s/i', $content) === 1)
			{
				return $file;
			}
		}

		return null;
	}

	// Retourne [ siteRelativePath => zipRelativePath ]
	public static function parse(string $manifestFile, string $type, string $element, string $folder, int $clientId): array
	{
		$xml = simplexml_load_file($manifestFile);
		if ($xml === false) throw new Exception("Manifeste XML invalide: $manifestFile");

		// Pour un composant, le manifeste distingue toujours site/admin via sa structure (bloc <files> racine = site,
		// <administration><files> = admin) : son bloc racine est donc TOUJOURS côté site, quelle que soit la valeur
		// de client_id (qui vaut fréquemment 1 en base pour un composant sans que ça signifie "admin-only").
		// Pour un module/template/librairie en revanche, toute l'extension appartient à un seul client, déterminé par client_id.
		$manifestClient = (string)($xml['client'] ?? '');
		$isAdminExtension = $type !== 'component' && ($manifestClient === 'administrator' || $clientId === 1);

		$siteBase = self::siteBasePath($type, $element, $folder, $isAdminExtension);

		$map = [];

		// Bloc <files> principal (site) + <administration><files> (admin)
		if (isset($xml->files))
		{
			self::mapFilesBlock($xml->files, $siteBase, $map);
		}
		if (isset($xml->administration->files))
		{
			$adminBase = self::siteBasePath($type, $element, $folder, true);
			self::mapFilesBlock($xml->administration->files, $adminBase, $map);
		}

		// Media : toujours à la racine média du site, quel que soit le type d'extension
		if (isset($xml->media))
		{
			$destination = (string)($xml->media['destination'] ?? $element);
			self::mapFilesBlock($xml->media, "media/$destination", $map);
		}

		// Langues (site + admin), copiées à plat dans language/{tag}/ (resp. administrator/language/{tag}/)
		if (isset($xml->languages))
		{
			self::mapLanguagesBlock($xml->languages, 'language', $map);
		}
		if (isset($xml->administration->languages))
		{
			self::mapLanguagesBlock($xml->administration->languages, 'administrator/language', $map);
		}

		return $map;
	}

	// Tous les préfixes de chemin site plausibles pour une extension (site + admin + media), utilisé pour exclure
	// une extension effectivement vérifiée du diff générique du noyau (évite le doublon "added_folder" + analyse détaillée).
	public static function basePaths(string $type, string $element, string $folder): array
	{
		return array_values(array_unique([
			self::siteBasePath($type, $element, $folder, false),
			self::siteBasePath($type, $element, $folder, true),
			'media/' . $element,
		]));
	}

	private static function siteBasePath(string $type, string $element, string $folder, bool $isAdmin): string
	{
		$adminPrefix = $isAdmin ? 'administrator/' : '';

		switch ($type)
		{
			case 'component':
				return $adminPrefix . 'components/' . $element;
			case 'module':
				return $adminPrefix . 'modules/' . $element;
			case 'plugin':
				return 'plugins/' . $folder . '/' . $element;
			case 'template':
				return $adminPrefix . 'templates/' . $element;
			case 'library':
				return 'libraries/' . $element;
			default:
				return $adminPrefix . $type . '/' . $element;
		}
	}

	// $filesNode peut contenir un attribut folder="sous-dossier-source-dans-le-zip", et des <filename>/<folder> enfants
	private static function mapFilesBlock(SimpleXMLElement $filesNode, string $siteBase, array &$map): void
	{
		$sourceFolder = (string)($filesNode['folder'] ?? '');
		$zipBase = $sourceFolder === '' ? '' : $sourceFolder . '/';

		foreach ($filesNode->filename as $filename)
		{
			$name = trim((string)$filename);
			if ($name === '') continue;

			$map["$siteBase/$name"] = $zipBase . $name;
		}

		foreach ($filesNode->folder as $folderNode)
		{
			$name = trim((string)$folderNode);
			if ($name === '') continue;

			self::mapDirectoryRecursive($zipBase . $name, "$siteBase/$name", $map);
		}
	}

	// Les <language> listent un chemin (relatif au folder="" du bloc <languages>) vers le fichier .ini source ;
	// destination = language/{tag}/{basename} (Joomla copie les fichiers de langue à plat par tag)
	private static function mapLanguagesBlock(SimpleXMLElement $languagesNode, string $siteBase, array &$map): void
	{
		$sourceFolder = (string)($languagesNode['folder'] ?? '');
		$zipBase = $sourceFolder === '' ? '' : $sourceFolder . '/';

		foreach ($languagesNode->language as $language)
		{
			$tag = (string)($language['tag'] ?? '');
			$path = trim((string)$language);
			if ($path === '' || $tag === '') continue;

			$map["$siteBase/$tag/" . basename($path)] = $zipBase . $path;
		}
	}

	// Un <folder> déclaré dans le manifeste doit être résolu récursivement au moment du hashing (on ne connaît
	// la liste des fichiers qu'il contient qu'en lisant le zip extrait) : on marque juste le mapping dossier ici,
	// ExtensionReference se charge de l'expansion réelle via expandDirectoryMappings().
	private static function mapDirectoryRecursive(string $zipRelDir, string $siteRelDir, array &$map): void
	{
		$map['__dir__' . $siteRelDir] = $zipRelDir;
	}

	// Étend les mappings de dossier (marqueurs '__dir__...') en mappings fichier par fichier, en listant réellement
	// le contenu du dossier correspondant dans l'archive extraite.
	public static function expandDirectoryMappings(array $map, string $extractedDir): array
	{
		$expanded = [];

		foreach ($map as $siteRelPath => $zipRelPath)
		{
			if (str_starts_with($siteRelPath, '__dir__') == false)
			{
				$expanded[$siteRelPath] = $zipRelPath;
				continue;
			}

			$siteRelDir = substr($siteRelPath, strlen('__dir__'));
			$fullZipDir = rtrim($extractedDir, '/\\') . '/' . $zipRelPath;

			if (is_dir($fullZipDir) == false) continue;

			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($fullZipDir, \RecursiveDirectoryIterator::SKIP_DOTS)
			);

			foreach ($iterator as $fileInfo)
			{
				if ($fileInfo->isDir()) continue;

				$fullPath = str_replace('\\', '/', $fileInfo->getPathname());
				$relWithinDir = substr($fullPath, strlen(rtrim(str_replace('\\', '/', $fullZipDir), '/')) + 1);

				$expanded["$siteRelDir/$relWithinDir"] = "$zipRelPath/$relWithinDir";
			}
		}

		return $expanded;
	}

}
