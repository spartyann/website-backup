<?php

namespace App\IntegrityCheck\Joomla;

use App\IntegrityCheck\Support\Downloader;
use App\Tools\PrintTools;
use Exception;

// Client pour la Vulnerable Extensions List (VEL) officielle de Joomla (extensions.joomla.org).
// Le flux est un flux communautaire en grande partie en texte libre (titres/descriptions non structurés) :
// à utiliser comme signal indicatif, pas comme source de vérité précise sur les versions affectées.
class VelFeed
{

	private const FEED_URL = 'https://extensions.joomla.org/index.php?option=com_vel&format=json';

	public static function fetchItems(string $cacheFile, int $ttlHours = 24): array
	{
		if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $ttlHours * 3600)
		{
			$cached = self::loadCache($cacheFile);
			if ($cached !== null) return $cached;
		}

		PrintTools::text("Téléchargement du flux VEL (extensions.joomla.org)...");

		$tmpFile = $cacheFile . '.tmp';

		$dir = dirname($cacheFile);
		if (is_dir($dir) == false) mkdir($dir, 0777, true);

		try
		{
			Downloader::downloadToFile(self::FEED_URL, $tmpFile);

			$items = self::loadCache($tmpFile);
			if ($items === null) throw new Exception("Réponse VEL invalide ou vide.");

			rename($tmpFile, $cacheFile);

			return $items;
		}
		catch (Exception $e)
		{
			if (is_file($tmpFile)) unlink($tmpFile);

			// En cas d'échec réseau, retomber sur un cache existant même expiré plutôt que de faire échouer la tâche
			$stale = self::loadCache($cacheFile);
			if ($stale !== null)
			{
				PrintTools::text("Echec du téléchargement VEL (" . $e->getMessage() . "), utilisation du cache existant.");
				return $stale;
			}

			throw new Exception("Impossible de récupérer le flux VEL: " . $e->getMessage(), 0, $e);
		}
	}

	private static function loadCache(string $file): ?array
	{
		if (is_file($file) == false) return null;

		$content = file_get_contents($file);
		if ($content === false) return null;

		$data = json_decode($content, true);
		if (is_array($data) == false || isset($data['data']['items']) == false || is_array($data['data']['items']) == false) return null;

		return $data['data']['items'];
	}

}
