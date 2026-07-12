<?php

namespace App\IntegrityCheck\Joomla;

use App\IntegrityCheck\Support\Downloader;
use PDO;

// Résout l'URL de téléchargement officielle d'une extension pour une version précise, via le mécanisme
// "Update Sites" de Joomla (#__update_sites / #__update_sites_extensions) — le même que Joomla utilise
// lui-même pour proposer les mises à jour depuis l'administration.
class JoomlaUpdateSiteResolver
{

	// Retourne l'URL de téléchargement (avec extra_query éventuel déjà appliqué) pour $element en version $installedVersion,
	// ou null si aucun update site ne publie cette version précise (ex: update site n'expose que la dernière version,
	// qui diffère de la version installée).
	public static function resolveDownloadUrl(PDO $pdo, string $prefix, int $extensionId, string $element, string $installedVersion, array $extraQueryOverrides, string $tmpDir): ?string
	{
		$stmt = $pdo->prepare(
			"SELECT us.location, us.extra_query
			 FROM `{$prefix}update_sites_extensions` use_ext
			 JOIN `{$prefix}update_sites` us ON us.update_site_id = use_ext.update_site_id
			 WHERE use_ext.extension_id = ? AND us.enabled = 1 AND us.type = 'extension'"
		);
		$stmt->execute([$extensionId]);

		foreach ($stmt->fetchAll() as $site)
		{
			$url = self::findDownloadUrlInUpdateXml($site['location'], $element, $installedVersion, $tmpDir);
			if ($url === null) continue;

			$extraQuery = $extraQueryOverrides[$element] ?? html_entity_decode($site['extra_query'] ?? '');
			if ($extraQuery !== '')
			{
				$url .= (str_contains($url, '?') ? '&' : '?') . $extraQuery;
			}

			return $url;
		}

		return null;
	}

	private static function findDownloadUrlInUpdateXml(string $updateSiteUrl, string $element, string $installedVersion, string $tmpDir): ?string
	{
		$tmpFile = $tmpDir . '/update_site_' . md5($updateSiteUrl) . '.xml';

		try
		{
			Downloader::downloadToFile($updateSiteUrl, $tmpFile, 15);

			$xml = simplexml_load_file($tmpFile);
			if ($xml === false) return null;

			foreach ($xml->update as $update)
			{
				if ((string)$update->element !== $element) continue;
				if ((string)$update->version !== $installedVersion) continue;

				foreach ($update->downloads->downloadurl as $downloadUrl)
				{
					if ((string)($downloadUrl['type'] ?? 'full') === 'full')
					{
						return trim((string)$downloadUrl);
					}
				}
			}

			return null;
		}
		catch (\Throwable $e)
		{
			return null;
		}
		finally
		{
			if (is_file($tmpFile)) unlink($tmpFile);
		}
	}

}
