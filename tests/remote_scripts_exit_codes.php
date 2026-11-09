<?php
/** Vérifie le code de sortie du processus, sans réseau ni serveur MySQL. */
$fixture = sys_get_temp_dir() . '/website-backup-exit-' . bin2hex(random_bytes(8));
mkdir($fixture, 0700);
$source = file_get_contents(dirname(__DIR__) . '/remote_scripts/script.php');
file_put_contents($fixture . '/script.php', $source);
file_put_contents($fixture . '/transport.php', <<<'STUB'
<?php
namespace WebsiteBackupExitTest;
foreach (['CURLOPT_FILE', 'CURLOPT_SSL_VERIFYPEER', 'CURLOPT_SSL_VERIFYHOST', 'CURLOPT_FOLLOWLOCATION',
    'CURLOPT_PROTOCOLS_STR', 'CURLOPT_CONNECTTIMEOUT', 'CURLOPT_TIMEOUT', 'CURLOPT_FAILONERROR',
    'CURLOPT_USERAGENT', 'CURLOPT_HTTPHEADER', 'CURLOPT_POST', 'CURLOPT_POSTFIELDS',
    'CURLINFO_HTTP_CODE', 'CURLINFO_CONTENT_TYPE'] as $index => $name) {
    if (!defined($name)) define($name, $index + 1);
}
function curl_init($url) { return (object) ['url' => $url, 'options' => []]; }
function curl_setopt_array($handle, $options) { $handle->options = $options; return getenv('WB_TEST_MODE') !== 'curl_options'; }
function curl_setopt($handle, $key, $value) { $handle->options[$key] = $value; return true; }
function curl_exec($handle) {
    $mode = getenv('WB_TEST_MODE');
    if ($mode === 'network') return false;
    $body = '{"success":true,"backups":[]}';
    if ($mode === 'invalid_json') $body = '{broken';
    if ($mode === 'invalid_list') $body = '{"success":false}';
    if ($mode === 'archive_size' || $mode === 'archive_success') {
        $body = str_contains($handle->url, 'list_local_backups')
            ? '{"success":true,"backups":[{"id":"site_2026-11-09__10-00-00.tar","name":"site_2026-11-09__10-00-00.tar","size":7}]}'
            : ($mode === 'archive_size' ? 'short' : 'archive');
    }
    if (str_contains($handle->url, 'dump_and_download_db')) $body = 'SELECT 1;';
    fwrite($handle->options[CURLOPT_FILE], $body);
    return true;
}
function curl_getinfo($handle, $key) {
    if ($key === CURLINFO_HTTP_CODE) return getenv('WB_TEST_MODE') === 'auth' ? 401 : 200;
    if (str_contains($handle->url, 'dump_and_download_db')) return 'application/sql';
    return str_contains($handle->url, 'download_local_backup') ? 'application/octet-stream' : 'application/json';
}
function exec($command, &$output, &$status) {
    $call = ($GLOBALS['mysqlCalls'] ?? 0) + 1;
    $GLOBALS['mysqlCalls'] = $call;
    $status = (getenv('WB_TEST_MODE') === 'mysql_root' || (getenv('WB_TEST_MODE') === 'mysql_import' && $call === 2)) ? 1 : 0;
    $output = [];
    return '';
}
function file_put_contents($path, $contents) {
    if (str_ends_with($path, '/dump.json')) {
        if (getenv('WB_TEST_MODE') === 'write_warning') trigger_error('Erreur d’écriture simulée', E_USER_WARNING);
        if (getenv('WB_TEST_MODE') === 'write_false') return false;
    }
    return \file_put_contents($path, $contents);
}
STUB);
$count = 0;
function runCase(string $name, ?string $config, int $expected, string $mode = ''): void {
    global $fixture, $count;
    if ($config === null) { if (is_file($fixture . '/config.php')) unlink($fixture . '/config.php'); }
    else file_put_contents($fixture . '/config.php', $config);
    $process = proc_open([PHP_BINARY, $fixture . '/script.php'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
        $pipes, null, array_merge(getenv(), ['WB_TEST_MODE' => $mode]));
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $actual = proc_close($process);
    if ($actual !== $expected || ($expected === 1 && !str_contains($stderr, 'Erreur :'))) {
        throw new RuntimeException($name . ': code ' . $actual . ' au lieu de ' . $expected . '\n' . $stderr . $stdout);
    }
    $count++;
}
function config(array $entries): string { return '<?php define("CONF", ' . var_export($entries, true) . ');'; }
$backup = ['enabled' => true, 'type' => 'local_backups', 'destination' => $fixture . '/archives',
    'api_url' => 'https://example.test/api.php', 'api_key' => 'test-key'];
$sql = ['enabled' => true, 'type' => 'dump_sql', 'urls' => ['https://example.test/api.php?operation=dump_and_download_db'],
    'api_password' => 'test-password', 'db_host' => 'localhost', 'db_user' => 'test', 'db_pwd' => 'test',
    'db_root_user' => 'test', 'db_root_pwd' => 'test', 'db_db' => 'test'];
$matomo = ['enabled' => true, 'type' => 'matomo_json', 'url' => 'https://example.test/data'];
try {
    runCase('Configuration absente', null, 1);
    runCase('Syntaxe de configuration invalide', '<?php define(', 1);
    runCase('CONF absent', '<?php', 1);
    runCase('CONF invalide', '<?php define("CONF", "bad");', 1);
    runCase('Entrée invalide', config(['bad']), 1);
    runCase('Booléen invalide', config([['enabled' => 'true']]), 1);
    runCase('Type inconnu', config([['enabled' => true, 'type' => 'unknown']]), 1);
    runCase('Option invalide', config([array_merge($backup, ['retention_days' => -1])]), 1);
    runCase('Destination non répertoire', config([array_merge($backup, ['destination' => $fixture . '/transport.php'])]), 1);
    runCase('Export sans URL', config([array_merge($sql, ['urls' => []])]), 1);
    runCase('Succès sans tâche', config([]), 0);
    runCase('Tâche désactivée', config([['enabled' => false]]), 0);
    // Isoler le transport et MySQL dans un namespace ; le point d'entrée et son catch restent exécutés.
    $mocked = preg_replace('/^<\?php/', '<?php namespace WebsiteBackupExitTest; use \\RuntimeException; use \\Throwable; require __DIR__ . "/transport.php";', $source, 1);
    file_put_contents($fixture . '/script.php', $mocked);
    foreach (['network', 'auth', 'invalid_json', 'invalid_list', 'archive_size', 'curl_options'] as $mode) {
        runCase('Téléchargement : ' . $mode, config([$backup]), 1, $mode);
    }
    foreach (['mysql_root', 'mysql_import'] as $mode) runCase('MySQL : ' . $mode, config([$sql]), 1, $mode);
    foreach (['write_false', 'write_warning', 'invalid_json'] as $mode) runCase('JSON : ' . $mode, config([$matomo]), 1, $mode);
    runCase('Succès du téléchargement', config([$backup]), 0, 'archive_success');
    runCase('Succès de l’import', config([$sql]), 0);
    runCase('Succès JSON', config([$matomo]), 0);
    echo $count . " codes de sortie vérifiés (erreur=1, succès=0)\n";
} finally {
    $remove = function (string $directory) use (&$remove): void {
        foreach (scandir($directory) as $name) {
            if ($name === '.' || $name === '..') continue;
            $path = $directory . '/' . $name;
            if (is_dir($path) && !is_link($path)) $remove($path); else unlink($path);
        }
        rmdir($directory);
    };
    $remove($fixture);
}
