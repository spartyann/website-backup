<?php

namespace App\IntegrityCheck\Support;

use Exception;

class Downloader
{

	// $timeoutSeconds borne le temps total de la requête : un hôte tiers lent/injoignable (fréquent avec des dizaines
	// d'update sites d'éditeurs différents) ne doit pas pouvoir bloquer toute une vérification. Le noyau Joomla
	// (gros téléchargement sur un hôte fiable) garde une valeur généreuse par défaut ; les appels vers des hôtes
	// tiers (update sites d'extensions) doivent passer une valeur bien plus courte.
	public static function downloadToFile(string $url, string $destFile, int $timeoutSeconds = 300): void
	{
		$fp = fopen($destFile, 'wb');
		if ($fp === false) throw new Exception("Cannot open destination file: $destFile");

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
		curl_setopt($ch, CURLOPT_USERAGENT, 'website-backup-integrity-check');
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeoutSeconds);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, $timeoutSeconds));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

		$success = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$error = curl_error($ch);
		curl_close($ch);
		fclose($fp);

		if ($success === false)
		{
			@unlink($destFile);
			throw new Exception("Download failed for $url: $error");
		}

		if ($httpCode < 200 || $httpCode >= 300)
		{
			@unlink($destFile);
			throw new Exception("Download failed for $url: HTTP $httpCode");
		}

		if (filesize($destFile) === 0)
		{
			@unlink($destFile);
			throw new Exception("Download failed for $url: empty file");
		}
	}

}
