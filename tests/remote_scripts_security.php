<?php
namespace WebsiteBackupRemoteTest;

use RuntimeException;

/** Transport cURL simulé dans un namespace, même si cURL est compilé en statique. */
foreach (['CURLOPT_FILE', 'CURLOPT_SSL_VERIFYPEER', 'CURLOPT_SSL_VERIFYHOST', 'CURLOPT_FOLLOWLOCATION',
    'CURLOPT_PROTOCOLS_STR', 'CURLOPT_CONNECTTIMEOUT', 'CURLOPT_TIMEOUT', 'CURLOPT_FAILONERROR',
    'CURLOPT_USERAGENT', 'CURLOPT_HTTPHEADER', 'CURLOPT_POST', 'CURLOPT_POSTFIELDS',
    'CURLINFO_HTTP_CODE', 'CURLINFO_CONTENT_TYPE'] as $index => $name) {
    if (!defined($name)) define($name, $index + 1);
}
function curl_init($url) { return (object) ['url' => $url, 'options' => [], 'response' => null]; }
function curl_setopt_array($handle, $options) { $handle->options = $options; return true; }
function curl_setopt($handle, $key, $value) { $handle->options[$key] = $value; return true; }
function curl_exec($handle) {
    $GLOBALS['lastRequest'] = $handle;
    $handle->response = array_shift($GLOBALS['responses']);
    if ($handle->response === null) throw new RuntimeException('Requête inattendue');
    fwrite($handle->options[CURLOPT_FILE], $handle->response['body']);
    return $handle->response['ok'] ?? true;
}
function curl_getinfo($handle, $key) {
    return $key === CURLINFO_HTTP_CODE ? $handle->response['status'] : $handle->response['type'];
}
function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
    $GLOBALS['checks']++;
}
function rejects($callback, $message) {
    try { $callback(); }
    catch (RuntimeException $exception) { check(true, $message); return; }
    throw new RuntimeException($message);
}
$checks = 0;
$fixture = sys_get_temp_dir() . '/website-backup-remote-test-' . bin2hex(random_bytes(8));
mkdir($fixture, 0700);
// Isoler la résolution des fonctions cURL sans désactiver la vérification TLS du vrai client.
$source = file_get_contents(dirname(__DIR__) . '/remote_scripts/script.php');
$source = preg_replace('/^<\?php/', '<?php namespace WebsiteBackupRemoteTest; use \\RuntimeException; use \\Throwable;', $source, 1);
file_put_contents($fixture . '/script.php', $source);
file_put_contents($fixture . '/config.php', '<?php define("CONF", []);');
require $fixture . '/script.php';
$name = 'site_2026-11-09__10-00-00.zip';
$conf = ['api_url' => 'https://backup.example/api.php', 'api_key' => 'test-key', 'destination' => $fixture . '/archives'];
$list = ['status' => 200, 'type' => 'application/json', 'body' => json_encode(['success' => true,
    'backups' => [['name' => $name, 'id' => $name, 'size' => 7]]])];
try {
    rejects(fn() => downloadFileWithCurl('http://backup.example/api.php', $fixture . '/file'), 'HTTP interdit');
    rejects(fn() => downloadFileWithCurl('https://user:pwd@backup.example/api.php', $fixture . '/file'), 'Identifiants URL interdits');
    rejects(fn() => downloadFileWithCurl('https://backup.example/api.php?apipwd=secret', $fixture . '/file'), 'Mot de passe URL interdit');
    rejects(fn() => downloadFileWithCurl('https://backup.example/api.php', $fixture . '/file', [], "key\r\nInjected: value"), 'Injection en-tête interdite');
    $responses = [['status' => 200, 'type' => 'application/sql; charset=utf-8', 'body' => 'SELECT 1;']];
    downloadFileWithCurl('https://backup.example/api.php?operation=dump_and_download_db', $fixture . '/file',
        ['apipwd' => 'password'], null, 'application/sql');
    check($lastRequest->options[CURLOPT_POST] === true
        && $lastRequest->options[CURLOPT_POSTFIELDS] === 'apipwd=password'
        && !str_contains($lastRequest->url, 'password'), 'Mot de passe dans le corps POST');
    check($lastRequest->options[CURLOPT_FOLLOWLOCATION] === false
        && $lastRequest->options[CURLOPT_SSL_VERIFYPEER] === true
        && $lastRequest->options[CURLOPT_SSL_VERIFYHOST] === 2, 'Transport sécurisé');
    foreach ([['status' => 302, 'type' => 'application/sql', 'body' => 'redirect'],
        ['status' => 401, 'type' => 'application/json', 'body' => '{"error":true}'],
        ['status' => 200, 'type' => 'application/json', 'body' => '{"success":false}'],
        ['status' => 200, 'type' => 'application/sql', 'body' => 'partial', 'ok' => false]] as $response) {
        $responses = [$response];
        rejects(fn() => downloadFileWithCurl('https://backup.example/api.php', $fixture . '/file',
            ['apipwd' => 'password'], null, 'application/sql'), 'Réponse invalide refusée');
        check(file_get_contents($fixture . '/file') === 'SELECT 1;', 'Fichier existant préservé');
    }
    $responses = [$list, ['status' => 200, 'type' => 'application/octet-stream', 'body' => 'archive']];
    downloadLocalBackups($conf);
    check(file_get_contents($conf['destination'] . '/' . $name) === 'archive', 'Archive téléchargée');
    check($lastRequest->options[CURLOPT_HTTPHEADER] === ['Authorization: Bearer test-key'], 'Clé dans en-tête Bearer');
    $responses = [$list];
    downloadLocalBackups($conf);
    check($responses === [], 'Archive de même taille ignorée');
    file_put_contents($conf['destination'] . '/' . $name, 'old');
    $responses = [$list, ['status' => 200, 'type' => 'application/octet-stream', 'body' => 'short']];
    rejects(fn() => downloadLocalBackups($conf), 'Archive tronquée refusée');
    check(file_get_contents($conf['destination'] . '/' . $name) === 'old', 'Ancienne archive préservée');
    symlink($fixture . '/file', $conf['destination'] . '/link_2026-11-09__10-00-00.zip');
    $badList = $list;
    $badList['body'] = json_encode(['success' => true, 'backups' => [['name' => '../escape.zip', 'id' => 'bad', 'size' => 7]]]);
    $responses = [$badList];
    rejects(fn() => downloadLocalBackups($conf), 'Nom distant dangereux refusé');
    $badList['body'] = json_encode(['success' => true, 'backups' => [['name' => 'link_2026-11-09__10-00-00.zip', 'id' => 'bad', 'size' => 7]]]);
    $responses = [$badList];
    rejects(fn() => downloadLocalBackups($conf), 'Lien local refusé');
    check(glob($fixture . '/.backup-*') === [] && glob($conf['destination'] . '/.backup-*') === [], 'Fichiers temporaires nettoyés');
    foreach ([['max_age_days' => 0], ['max_age_days' => '7'], ['retention_days' => -1], ['retention_days' => 365001],
        ['min_versions' => -1], ['min_versions' => '2']] as $invalidOptions) {
        rejects(fn() => downloadLocalBackups(array_merge($conf, $invalidOptions)), 'Options de rétention strictes');
    }
    $responses = [$list];
    // Éviter un nouveau transfert pour vérifier seulement l'URL du listing filtré.
    file_put_contents($conf['destination'] . '/' . $name, 'archive');
    downloadLocalBackups(array_merge($conf, ['max_age_days' => 7]));
    check(str_contains($lastRequest->url, 'max_age_days=7'), 'Filtre d’âge transmis à l’API');
    $lock = fopen($conf['destination'] . '/.website-backup.lock', 'c');
    flock($lock, LOCK_EX);
    rejects(fn() => downloadLocalBackups($conf), 'Synchronisation concurrente refusée');
    fclose($lock);

    $retentionDir = $fixture . '/retention';
    mkdir($retentionDir);
    $daily = ['site_2026-09-01__12-00-00_daily.tar', 'site_2026-09-02__12-00-00_daily.zip', 'site_2026-09-03__12-00-00_daily.tar'];
    $database = ['db_2026-09-01__12-00-00.tar', 'db_2026-09-02__12-00-00.zip', 'db_2026-09-03__12-00-00.tar'];
    $preserved = ['site_2026-09-01__12-00-00_weekly.tar', 'recent_2026-11-08__12-00-00.tar',
        'edge_2026-10-10__12-00-00.tar', 'safe_2026-09-01__12-00-00.tar', 'notes.txt',
        'invalid_2026-02-30__12-00-00.zip', 'ambiguous_2026-09-01__12-00-00_2026-09-02__12-00-00.tar'];
    foreach (array_merge($daily, $database, $preserved) as $file) file_put_contents($retentionDir . '/' . $file, 'data');
    mkdir($retentionDir . '/folder_2026-09-01__12-00-00.tar');
    file_put_contents($retentionDir . '/folder_2026-09-01__12-00-00.tar/keep.txt', 'nested');
    symlink($fixture . '/file', $retentionDir . '/safe_2026-10-01__12-00-00.tar');
    touch($retentionDir . '/' . $daily[0], time());
    touch($retentionDir . '/' . $daily[2], 946684800);
    $timezone = new \DateTimeZone('UTC');
    $now = new \DateTimeImmutable('2026-11-09 12:00:00', $timezone);
    pruneLocalBackups($retentionDir, 30, 2, $timezone, $now);
    check(!file_exists($retentionDir . '/' . $daily[0]) && file_exists($retentionDir . '/' . $daily[1])
        && file_exists($retentionDir . '/' . $daily[2]), 'Deux versions par série, indépendantes du mtime et du format');
    check(!file_exists($retentionDir . '/' . $database[0]) && file_exists($retentionDir . '/' . $database[1])
        && file_exists($retentionDir . '/' . $database[2]), 'Minimum indépendant pour la base');
    foreach ($preserved as $file) check(file_exists($retentionDir . '/' . $file), 'Fichier conservé : ' . $file);
    check(file_exists($retentionDir . '/folder_2026-09-01__12-00-00.tar/keep.txt'), 'Aucune récursion');
    pruneLocalBackups($retentionDir, 30, 1, $timezone, $now);
    check(file_exists($retentionDir . '/safe_2026-09-01__12-00-00.tar')
        && is_link($retentionDir . '/safe_2026-10-01__12-00-00.tar'), 'Lien non compté comme version et jamais supprimé');
    pruneLocalBackups($retentionDir, 30, 0, $timezone, $now);
    check(!file_exists($retentionDir . '/' . $daily[2]) && file_exists($retentionDir . '/recent_2026-11-08__12-00-00.tar')
        && file_exists($retentionDir . '/edge_2026-10-10__12-00-00.tar'), 'Minimum zéro et limite d’âge stricte');
    check(backupNameMetadata('site_2026-03-29__02-30-00.tar', new \DateTimeZone('Europe/Paris')) === null,
        'Heure inexistante dans le fuseau refusée');

    $oldName = 'cleanup_2001-01-01__00-00-00.tar';
    file_put_contents($conf['destination'] . '/' . $oldName, 'old');
    $cleanupConf = array_merge($conf, ['retention_days' => 30, 'min_versions' => 0]);
    $responses = [['status' => 500, 'type' => 'application/json', 'body' => 'error']];
    rejects(fn() => downloadLocalBackups($cleanupConf), 'Erreur de listing interrompt la purge');
    check(file_exists($conf['destination'] . '/' . $oldName), 'Anciennes copies préservées après erreur réseau');
    file_put_contents($conf['destination'] . '/' . $name, 'old');
    $responses = [$list, ['status' => 200, 'type' => 'application/octet-stream', 'body' => 'short']];
    rejects(fn() => downloadLocalBackups($cleanupConf), 'Erreur de taille interrompt la purge');
    check(file_exists($conf['destination'] . '/' . $oldName), 'Anciennes copies préservées après erreur de transfert');
    $responses = [['status' => 200, 'type' => 'application/json', 'body' => '{"success":true,"backups":[]}']];
    downloadLocalBackups($conf);
    check(file_exists($conf['destination'] . '/' . $oldName), 'Purge désactivée par défaut');
    $responses = [['status' => 200, 'type' => 'application/json', 'body' => '{"success":true,"backups":[]}']];
    downloadLocalBackups($cleanupConf);
    check(!file_exists($conf['destination'] . '/' . $oldName), 'Purge après synchronisation réussie, même sans nouvelle archive');
    echo $checks . " vérifications réussies\n";
} finally {
    $remove = function (string $directory) use (&$remove): void {
        foreach (scandir($directory) as $name) {
            if ($name === '.' || $name === '..') continue;
            $path = $directory . '/' . $name;
            if (is_dir($path) && !is_link($path)) $remove($path);
            else unlink($path);
        }
        rmdir($directory);
    };
    $remove($fixture);
}
