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
    echo $checks . " vérifications réussies\n";
} finally {
    foreach (glob($fixture . '/archives/*') as $file) unlink($file);
    if (is_dir($fixture . '/archives')) rmdir($fixture . '/archives');
    foreach (glob($fixture . '/*') as $file) unlink($file);
    rmdir($fixture);
}
