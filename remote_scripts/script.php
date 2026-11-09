<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Exécution CLI uniquement');
}

/** HTTPS uniquement, sans redirection : aucun secret transmis à un autre serveur. */
function downloadFileWithCurl($url, $destination, array $post = [], ?string $apiKey = null, ?string $contentType = null) {
    $parts = parse_url($url);
    if ($parts === false || ($parts['scheme'] ?? '') !== 'https' || isset($parts['user']) || isset($parts['pass'])) {
        throw new RuntimeException('Une URL HTTPS sans identifiants est requise');
    }
    parse_str($parts['query'] ?? '', $query);
    if (isset($query['apipwd'])) throw new RuntimeException('Retirer apipwd de l’URL et utiliser api_password');
    if ($apiKey !== null && ($apiKey === '' || preg_match('/[\s\x00-\x1f\x7f]/', $apiKey))) {
        throw new RuntimeException('Clé API invalide');
    }
    $temporary = tempnam(dirname($destination), '.backup-download-');
    if ($temporary === false) throw new RuntimeException('Fichier temporaire indisponible');
    $file = null;
    $ch = null;
    try {
        if (!chmod($temporary, 0600)) throw new RuntimeException('Permissions du fichier temporaire indisponibles');
        $file = fopen($temporary, 'wb');
        if ($file === false) throw new RuntimeException('Fichier temporaire inaccessible');
        $ch = curl_init($url);
        $configured = curl_setopt_array($ch, [
            CURLOPT_FILE => $file,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            (defined('CURLOPT_PROTOCOLS_STR') ? CURLOPT_PROTOCOLS_STR : CURLOPT_PROTOCOLS) =>
                defined('CURLOPT_PROTOCOLS_STR') ? 'https' : CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 3600,
            CURLOPT_FAILONERROR => true,
            CURLOPT_USERAGENT => 'WebsiteBackup-Remote/1.0',
            CURLOPT_HTTPHEADER => $apiKey === null ? [] : ['Authorization: Bearer ' . $apiKey],
        ]);
        if (!$configured) throw new RuntimeException('Configuration cURL impossible');
        if ($post !== []) {
            if (!curl_setopt($ch, CURLOPT_POST, true)
                || !curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post))) {
                throw new RuntimeException('Configuration POST impossible');
            }
        }
        $result = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $receivedType = strtolower(trim(explode(';', curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '')[0]));
        if ($result === false || $status !== 200 || ($contentType !== null && $receivedType !== $contentType)) {
            throw new RuntimeException('Téléchargement refusé ou interrompu (HTTP ' . $status . ')');
        }
        fclose($file);
        $file = null;
        if (!rename($temporary, $destination)) throw new RuntimeException('Impossible de finaliser le téléchargement');
        return true;
    } finally {
        if (is_resource($file)) fclose($file);
        // Le handle cURL est libéré en quittant la fonction (PHP 8).
        if (is_file($temporary)) unlink($temporary);
    }
}

/** Date et série déduites uniquement du nom ; aucune utilisation de filemtime(). */
function backupNameMetadata(string $name, \DateTimeZone $timezone): ?array {
    if ($name === '' || str_contains($name, '/') || str_contains($name, '\\')
        || preg_match('/[\x00-\x1f\x7f]/', $name)
        || !preg_match('/^(.*?)(\d{4}-\d{2}-\d{2}__\d{2}-\d{2}-\d{2})(.*)\.(tar|zip)$/D', $name, $parts)
        || preg_match_all('/\d{4}-\d{2}-\d{2}__\d{2}-\d{2}-\d{2}/', $name) !== 1) return null;
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d__H-i-s', $parts[2], $timezone);
    if ($date === false || $date->format('Y-m-d__H-i-s') !== $parts[2]) return null;
    return ['date' => $date, 'series' => json_encode([$parts[1], $parts[3]], JSON_THROW_ON_ERROR)];
}

function backupIntegerOption(array $conf, string $name, ?int $default, int $minimum, int $maximum): ?int {
    $value = $conf[$name] ?? $default;
    if ($value !== null && (!is_int($value) || $value < $minimum || $value > $maximum)) {
        throw new RuntimeException('Option invalide : ' . $name);
    }
    return $value;
}

/** Ne supprime que les archives régulières reconnues à la racine du dossier configuré. */
function pruneLocalBackups(string $directory, int $days, int $minimum, \DateTimeZone $timezone,
    ?\DateTimeImmutable $now = null): void {
    $cutoff = ($now ?? new \DateTimeImmutable('now', $timezone))->sub(new \DateInterval('P' . $days . 'D'));
    $names = scandir($directory);
    if ($names === false) throw new RuntimeException('Liste des copies locales inaccessible');
    $series = [];
    foreach ($names as $name) {
        $metadata = backupNameMetadata($name, $timezone);
        if ($metadata === null) continue;
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        clearstatcache(true, $path);
        $stat = @lstat($path);
        if ($stat === false || ($stat['mode'] & 0170000) !== 0100000) continue;
        $series[$metadata['series']][] = ['name' => $name, 'date' => $metadata['date'], 'stat' => $stat];
    }
    foreach ($series as $files) {
        usort($files, static fn($a, $b) => ($b['date'] <=> $a['date']) ?: strcmp($a['name'], $b['name']));
        foreach (array_slice($files, $minimum) as $file) {
            if ($file['date'] >= $cutoff) continue;
            $path = $directory . DIRECTORY_SEPARATOR . $file['name'];
            clearstatcache(true, $path);
            $current = @lstat($path);
            // Un fichier remplacé depuis l'inventaire est conservé pour la prochaine exécution.
            if ($current === false || ($current['mode'] & 0170000) !== 0100000
                || $current['ino'] !== $file['stat']['ino'] || $current['dev'] !== $file['stat']['dev']
                || $current['size'] !== $file['stat']['size'] || $current['mtime'] !== $file['stat']['mtime']) continue;
            if (!unlink($path)) throw new RuntimeException('Suppression de la copie locale impossible');
            echo 'Supprimé (rétention) : ' . $file['name'] . PHP_EOL;
        }
    }
}

function downloadLocalBackups(array $conf): void {
    $maxAge = backupIntegerOption($conf, 'max_age_days', null, 1, 365000);
    $retention = backupIntegerOption($conf, 'retention_days', null, 1, 365000);
    $minimum = backupIntegerOption($conf, 'min_versions', 1, 0, 1000000);
    $timezone = new \DateTimeZone($conf['backup_timezone'] ?? date_default_timezone_get());
    $directory = $conf['destination'];
    if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new RuntimeException('Destination inaccessible');
    $directory = realpath($directory);
    if ($directory === false || !is_writable($directory)) throw new RuntimeException('Destination inaccessible');
    // Deux synchronisations du même dossier ne doivent pas purger en parallèle.
    $lockPath = $directory . '/.website-backup.lock';
    if (is_link($lockPath)) throw new RuntimeException('Lien symbolique interdit pour le verrou');
    $mask = umask(0077);
    try { $lock = fopen($lockPath, 'c'); }
    finally { umask($mask); }
    if ($lock === false) throw new RuntimeException('Verrou de synchronisation inaccessible');
    try {
        $stat = fstat($lock);
        $current = lstat($lockPath);
        if ($current === false || $stat === false || ($current['mode'] & 0170000) !== 0100000
            || $current['ino'] !== $stat['ino'] || $current['dev'] !== $stat['dev']
            || !flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Synchronisation déjà en cours ou verrou invalide');
        syncLocalBackups($conf, $directory, $maxAge, $timezone);
        // Une erreur réseau, de métadonnées ou de taille interrompt le script AVANT toute purge.
        if ($retention !== null) pruneLocalBackups($directory, $retention, $minimum, $timezone);
    } finally {
        fclose($lock);
    }
}

function syncLocalBackups(array $conf, string $directory, ?int $maxAge, \DateTimeZone $timezone): void {
    $url = $conf['api_url'];
    if (str_contains($url, '?') || str_contains($url, '#')) throw new RuntimeException('api_url doit désigner api.php sans paramètres');
    $listFile = tempnam($directory, '.backup-list-');
    if ($listFile === false) throw new RuntimeException('Fichier temporaire indisponible');
    try {
        $params = ['operation' => 'list_local_backups'];
        if ($maxAge !== null) $params['max_age_days'] = $maxAge;
        downloadFileWithCurl($url . '?' . http_build_query($params), $listFile, [], $conf['api_key'], 'application/json');
        $list = json_decode(file_get_contents($listFile), true, 512, JSON_THROW_ON_ERROR);
        if (($list['success'] ?? false) !== true || !is_array($list['backups'] ?? null)) {
            throw new RuntimeException('Liste des archives invalide');
        }
        foreach ($list['backups'] as $backup) {
            $name = $backup['name'] ?? null;
            $id = $backup['id'] ?? null;
            $size = $backup['size'] ?? null;
            if (!is_string($name) || !is_string($id) || backupNameMetadata($name, $timezone) === null
                || !is_int($size) || $size < 0) throw new RuntimeException('Métadonnées d’archive invalides');
            $destination = $directory . DIRECTORY_SEPARATOR . $name;
            if (is_link($destination)) throw new RuntimeException('Lien symbolique interdit dans la destination');
            if (is_file($destination) && filesize($destination) === $size) {
                echo 'Déjà présent : ' . $name . PHP_EOL;
                continue;
            }
            // Une archive existante reste intacte si le transfert ou la vérification de taille échoue.
            $staging = tempnam($directory, '.backup-archive-');
            if ($staging === false) throw new RuntimeException('Fichier temporaire indisponible');
            try {
                downloadFileWithCurl($url . '?' . http_build_query(['operation' => 'download_local_backup', 'id' => $id]),
                    $staging, [], $conf['api_key'], 'application/octet-stream');
                clearstatcache(true, $staging);
                if (filesize($staging) !== $size) throw new RuntimeException('Taille de l’archive incorrecte');
                if (!rename($staging, $destination)) throw new RuntimeException('Impossible de conserver l’archive');
                echo 'Téléchargé : ' . $name . PHP_EOL;
            } finally {
                if (is_file($staging)) unlink($staging);
            }
        }
    } finally {
        if (is_file($listFile)) unlink($listFile);
    }
}

/** Les mots de passe MySQL restent hors de la ligne de commande et sont supprimés après usage. */
function runMysql(string $host, string $user, string $password, string $arguments): bool {
    $credentials = tempnam(sys_get_temp_dir(), 'website-backup-mysql-');
    if ($credentials === false) throw new RuntimeException('Fichier MySQL temporaire indisponible');
    try {
        if (!chmod($credentials, 0600)) throw new RuntimeException('Permissions MySQL indisponibles');
        $quote = static fn(string $value): string => '"' . str_replace(
            ["\\", '"', "\n", "\r"], ["\\\\", '\\"', '\\n', '\\r'], $value) . '"';
        $contents = "[client]\n" . 'host=' . $quote($host) . "\nuser=" . $quote($user)
            . "\npassword=" . $quote($password) . "\n";
        if (file_put_contents($credentials, $contents) !== strlen($contents)) throw new RuntimeException('Écriture MySQL impossible');
        $output = [];
        $status = 1;
        exec('mysql --defaults-extra-file=' . escapeshellarg($credentials) . ' ' . $arguments, $output, $status);
        return $status === 0;
    } finally {
        unlink($credentials);
    }
}

function importViaMysqlCommand($sqlFile, $host, $user, $pass, $rootuser, $rootpass, $database): bool {
    if (!runMysql($host, $rootuser, $rootpass, '-e ' . escapeshellarg('SET GLOBAL max_allowed_packet=1073741824;'))) {
        throw new RuntimeException('Impossible de modifier max_allowed_packet');
    }
    return runMysql($host, $user, $pass, '--max_allowed_packet=1073741824 --database='
        . escapeshellarg($database) . ' < ' . escapeshellarg($sqlFile));
}

// Les avertissements non masqués (I/O, configuration, etc.) doivent aussi faire échouer le processus.
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) return false;
    throw new \ErrorException($message, 0, $severity, $file, $line);
});
try {
    if (!is_readable(__DIR__ . '/config.php')) throw new RuntimeException('Fichier config.php absent ou illisible');
    try { require_once __DIR__ . '/config.php'; }
    catch (Throwable $exception) { throw new RuntimeException('Impossible de charger config.php', 0, $exception); }
    if (!defined('CONF') || !is_array(CONF)) throw new RuntimeException('CONF doit être un tableau');
    foreach (CONF as $conf) {
        if (!is_array($conf) || (isset($conf['enabled']) && !is_bool($conf['enabled']))) {
            throw new RuntimeException('Entrée de configuration invalide');
        }
        if (($conf['enabled'] ?? false) !== true) continue;
        if ($conf['type'] === 'local_backups') {
            downloadLocalBackups($conf);
        } elseif ($conf['type'] === 'dump_sql') {
            if (!is_array($conf['urls'] ?? null) || $conf['urls'] === []) {
                throw new RuntimeException('Aucune URL d’export SQL configurée');
            }
            foreach ($conf['urls'] as $url) {
                $file = tempnam(sys_get_temp_dir(), 'website-backup-import-');
                if ($file === false) throw new RuntimeException('Fichier SQL temporaire indisponible');
                try {
                    downloadFileWithCurl($url, $file, ['apipwd' => $conf['api_password']], null, 'application/sql');
                    if (!importViaMysqlCommand($file, $conf['db_host'], $conf['db_user'], $conf['db_pwd'],
                        $conf['db_root_user'], $conf['db_root_pwd'], $conf['db_db'])) {
                        throw new RuntimeException('Échec de l’import SQL');
                    }
                    echo "Import réussi\n";
                } finally {
                    if (is_file($file)) unlink($file);
                }
            }
        } elseif ($conf['type'] === 'matomo_json') {
            $file = __DIR__ . '/dump.json';
            downloadFileWithCurl($conf['url'], $file);
            $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
            $json = json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
            if (file_put_contents($file, $json) !== strlen($json)) throw new RuntimeException('Écriture JSON impossible');
        } else {
            throw new RuntimeException('Type de tâche inconnu');
        }
    }
} catch (Throwable $exception) {
    // Même un stderr indisponible ne doit pas remplacer le code de sortie attendu.
    @fwrite(STDERR, 'Erreur : ' . $exception->getMessage() . PHP_EOL);
    exit(1);
} finally {
    restore_error_handler();
}
