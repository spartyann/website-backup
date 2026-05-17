<?php

namespace App\Google;

use App\ExceptionWithCustomTrace;
use App\Tools\FileTools;
use DateTime;
use Google\Service\Webmasters;
use PDO;

class SearchConsoleDownloader
{

	public static function download(
		string $google_oauth_client_file,
		string $google_token_php_file,
		string $siteUrl,
		array $searchTypes,
		?array $destDB = null
	) : array
	{
		
		$res = [];

		$dateStart = date('Y-m-d', strtotime('-30 days'));
		$dateEnd   = date('Y-m-d', strtotime('-3 days')); // GSC a ~3 jours de délai
		$rowLimit  = 25000; // Max par requête API

		$client  = self::getGoogleClient($google_oauth_client_file, $google_token_php_file);
		$service = new Webmasters($client);

		$pdo     = self::getDB($destDB['host'], $destDB['port'], $destDB['db_name'], $destDB['user'], $destDB['pwd']);

		$table_name = $destDB['table_name'];

		self::ensureTable($pdo, $table_name);

		$totalInserted = 0;
		
		foreach ($searchTypes as $searchType) {
			$startRow = 0;

			do {
				//echo "  Récupération à partir de la ligne {$startRow}...\n";

				$rows = self::fetchKeywords($service, $siteUrl, $searchType, $dateStart, $dateEnd, $rowLimit, $startRow);

				if (empty($rows)) {
					//echo "  → Aucune ligne, fin de pagination.\n";
					break;
				}

				$inserted       = self::insertRows($pdo, $table_name, $searchType, $rows, $siteUrl, $dateStart, $dateEnd);
				$totalInserted += $inserted;
				$startRow      += count($rows);

				$res[] = "$searchType: " . count($rows) . " récupérées | {$inserted} affectées\n";

			} while (count($rows) === $rowLimit);
		}

		$res[] = "Terminé. Total traité : {$totalInserted} lignes.\n";

		return $res;
	}

	
	// ── 1. Authentification OAuth2 ────────────────────────────────────────────────
	private static function getGoogleClient(string $google_oauth_client_file, string $google_token_php_file): \Google\Client
	{
		$client = new \Google\Client();
		$client->setApplicationName('GSC Importer');
		$client->setAuthConfig(require($google_oauth_client_file));
		$client->addScope(Webmasters::WEBMASTERS_READONLY);
		$client->setAccessType('offline');
		$client->setPrompt('consent');

		// Exécutions suivantes : on charge le token sauvegardé
		$client->setAccessToken(require($google_token_php_file));

		// Rafraîchit automatiquement si expiré
		
		if ($client->isAccessTokenExpired()) {
			if (!$client->getRefreshToken()) {
				// Refresh token manquant → supprimer le token et relancer
				unlink($google_token_php_file);

				throw new \Exception(
					"Refresh token expiré ou absent. Supprime {$google_token_php_file} et relance le script."
				);
			}

			$newToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());

			self::writeConfFile($newToken, $google_token_php_file);
		}
		
		return $client;
	}

	private static function writeConfFile(array $data, string $path): bool
	{
		$export  = var_export($data, true);
		$content = "<?php\n\nreturn " . $export . ";\n";

		return file_put_contents($path, $content) !== false;
	}


	// ── 2. Récupération des mots-clés (Search Analytics) ─────────────────────────
	private static function fetchKeywords(
		\Google\Service\Webmasters $service,
		string $siteUrl,
    	string $searchType,
		string $dateStart,
		string $dateEnd,
		int $rowLimit,
		int $startRow = 0
	): array {
		$request = new \Google\Service\Webmasters\SearchAnalyticsQueryRequest();
		$request->setStartDate($dateStart);
		$request->setEndDate($dateEnd);
		$request->setDimensions(['date', 'query', 'page', 'country', 'device']);
		$request->setSearchType($searchType);
		$request->setRowLimit($rowLimit);
		$request->setStartRow($startRow);
		$request->setDataState('final');

		$response = $service->searchanalytics->query($siteUrl, $request);
		return $response->getRows() ?? [];
	}


	// ── 3. Connexion MariaDB ──────────────────────────────────────────────────────
	private static function getDB(
		string $dbHost,
		int $dbPort,
		string $dbName,
		string $dbUser,
		string $dbPass
	): \PDO {
		$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

		return new PDO($dsn, $dbUser, $dbPass, [
			PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		]);
	}


	// ── 4. Création de la table si elle n'existe pas ──────────────────────────────
	private static function ensureTable(PDO $pdo, string $table_name): void
	{
		$pdo->exec(<<<SQL
			CREATE TABLE IF NOT EXISTS {$table_name} (
				id             INT AUTO_INCREMENT PRIMARY KEY,
				site_url       VARCHAR(255)  NOT NULL,
				search_type    VARCHAR(20)   NOT NULL DEFAULT 'web',
				date_day       DATE          NOT NULL,
				query          VARCHAR(500)  NOT NULL,
				page           VARCHAR(1000),
				page_path      VARCHAR(1000),
				country        VARCHAR(10),
				device         VARCHAR(20),
				date_start     DATE          NOT NULL,
				date_end       DATE          NOT NULL,
				clicks         INT           DEFAULT 0,
				impressions    INT           DEFAULT 0,
				ctr            DECIMAL(8,4)  DEFAULT 0,
				position       DECIMAL(8,2)  DEFAULT 0,
				created_at     DATETIME      DEFAULT CURRENT_TIMESTAMP,
				UNIQUE KEY uq_keyword_period
					(site_url(100), search_type, date_day, query(191), page(191), country, device),
				INDEX idx_query       (query(191)),
				INDEX idx_date_day    (date_day),
				INDEX idx_search_type (search_type, date_day),
				INDEX idx_site        (site_url(100))
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

		SQL);
	}


	// ── 5. Insertion en batch (avec upsert) ───────────────────────────────────────
	private static function insertRows(\PDO $pdo, string $table_name, $searchType, array $rows, string $siteUrl, string $dateStart, string $dateEnd): int
	{
		if (empty($rows)) return 0;

		$sql = <<<SQL
			INSERT INTO {$table_name}
				(site_url, search_type, date_day, query, page, page_path, country, device,
				date_start, date_end, clicks, impressions, ctr, position)
			VALUES
				(:site, :search_type, :date_day, :query, :page, :page_path, :country, :device,
				:date_start, :date_end, :clicks, :impressions, :ctr, :position)
			ON DUPLICATE KEY UPDATE
				clicks      = VALUES(clicks),
				impressions = VALUES(impressions),
				ctr         = VALUES(ctr),
				position    = VALUES(position)
		SQL;

		$stmt  = $pdo->prepare($sql);
		$count = 0;

		$pdo->beginTransaction();
		try {
			foreach ($rows as $row) {
				$keys = $row->getKeys(); // [query, page, country, device]
				$stmt->execute([
					':site'        => $siteUrl,
					':search_type' => $searchType,
					':date_day'    => $keys[0] ?? null, // ← date granulaire
					':query'       => $keys[1] ?? '',
					':page'        => $keys[2] ?? null,
					':page_path'   => (($keys[2]?? null) == null ? null : parse_url($keys[2], PHP_URL_PATH)),
					':country'     => $keys[3] ?? null,
					':device'      => $keys[4] ?? null,
					':date_start'  => $dateStart,
					':date_end'    => $dateEnd,
					':clicks'      => (int)   $row->getClicks(),
					':impressions' => (int)   $row->getImpressions(),
					':ctr'         => (float) $row->getCtr(),
					':position'    => (float) $row->getPosition(),
				]);
				$count++;
			}
			$pdo->commit();
		} catch (\Exception $e) {
			$pdo->rollBack();
			throw $e;
		}

		return $count;
	}



}
