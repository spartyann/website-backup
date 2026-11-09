<?php
/** Tests isolés de l'API : php tests/api_security.php (sans Composer ni données de production). */
$root = dirname(__DIR__);
$fixture = sys_get_temp_dir() . '/website-backup-test-' . bin2hex(random_bytes(8));
mkdir($fixture, 0700);
mkdir($fixture . '/vendor');
mkdir($fixture . '/archives');
mkdir($fixture . '/archives/temp');
$archive = 'site_2026-11-09__10-00-00.zip';
file_put_contents($fixture . '/archives/' . $archive, 'archive-content');
file_put_contents($fixture . '/outside_2026-11-09__10-00-00.zip', 'private');
file_put_contents($fixture . '/archives/notes.zip', 'not a backup');
file_put_contents($fixture . '/archives/temp/backup.zip', 'unfinished');
symlink($fixture . '/outside_2026-11-09__10-00-00.zip', $fixture . '/archives/link_2026-11-09__10-00-00.zip');
copy($root . '/src/api.php', $fixture . '/api.php');
// Les classes métier sont doublées pour ne jamais lancer de sauvegarde ni toucher à une base.
file_put_contents($fixture . '/vendor/autoload.php', <<<'STUB'
<?php
namespace Config {
    class Config {
        public const UI_PASSWORD = 'test-password';
        public const LOCAL_BACKUP_API_KEY = 'test-key';
        public static function localStorageBackupDir() { return dirname(__DIR__) . '/archives'; }
        public static function groups() { return ['site' => ['items' => [['google_auth' => ['private_key' => 'SECRET']]]]]; }
        public static function tasks() { return ['checks' => [['name' => 'check', 'task' => 'integrity', 'folder_root' => self::localStorageBackupDir()]]]; }
        public static function downloadDBApi() { return ['db' => []]; }
    }
}
namespace App {
    class Backup {
        public static function dumpDb($path, $item, &$notification) { file_put_contents($path, 'SELECT 1;'); }
        public static function run($group) {}
    }
    class Tasks {
        public static function runSingleTask($group, $name) { throw new \RuntimeException('SECRET INTERNAL PATH'); }
    }
}
namespace {
STUB
    . 'require ' . var_export($root . '/src/App/LocalBackups.php', true) . '; require '
    . var_export($root . '/src/App/Tools/FileTools.php', true) . '; }');
file_put_contents($fixture . '/runner.php', <<<'RUNNER'
<?php
$request = json_decode(base64_decode($argv[1]), true);
$_GET = $request['get'] ?? [];
$_POST = $request['post'] ?? [];
$_SERVER['REQUEST_METHOD'] = $request['method'] ?? 'POST';
if (isset($request['bearer'])) $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer ' . $request['bearer'];
register_shutdown_function(function () { fwrite(STDERR, '\nSTATUS=' . (http_response_code() ?: 200)); });
require __DIR__ . '/api.php';
RUNNER);
$count = 0;
function check(bool $condition, string $message): void {
    global $count;
    if (!$condition) throw new RuntimeException($message);
    $count++;
}
function request(array $request): array {
    global $fixture;
    $process = proc_open([PHP_BINARY, $fixture . '/runner.php', base64_encode(json_encode($request))],
        [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $body = stream_get_contents($pipes[1]);
    $errors = stream_get_contents($pipes[2]);
    fclose($pipes[1]); fclose($pipes[2]);
    $exit = proc_close($process);
    if ($exit !== 0 || !preg_match('/STATUS=(\d+)/', $errors, $match)) throw new RuntimeException($errors);
    return [(int) $match[1], $body];
}
function removeFixture(string $directory): void {
    foreach (scandir($directory) as $name) {
        if ($name === '.' || $name === '..') continue;
        $path = $directory . '/' . $name;
        if (is_dir($path) && !is_link($path)) removeFixture($path);
        else unlink($path);
    }
    rmdir($directory);
}
try {
    check(request(['post' => ['operation' => 'load']])[0] === 401, 'Mot de passe obligatoire');
    check(request(['post' => ['operation' => 'load', 'apipwd' => ['test-password']]])[0] === 401, 'Mot de passe tableau refusé');
    check(request(['post' => ['operation' => 'load', 'apipwd' => '0']])[0] === 401, 'Comparaison stricte');
    check(request(['get' => ['apipwd' => 'test-password'], 'post' => ['operation' => 'load', 'apipwd' => 'test-password']])[0] === 400, 'Secret en URL refusé même avec POST');
    check(request(['method' => 'GET', 'get' => ['operation' => 'run_backup'], 'bearer' => 'test-key'])[0] === 405, 'GET ne lance pas de sauvegarde');
    foreach (['load', 'login', 'run_backup', 'run_tasks', 'run_single_task', 'delete_item', 'dump_and_download_db'] as $operation) {
        check(request(['post' => ['operation' => $operation], 'bearer' => 'test-key'])[0] === 403, 'Clé limitée : ' . $operation);
    }
    check(request(['post' => ['operation' => 'load', 'apipwd' => 'test-password'], 'bearer' => 'wrong'])[0] === 401, 'Pas de repli après une clé invalide');
    [$status, $body] = request(['post' => ['operation' => 'load', 'apipwd' => 'test-password']]);
    check($status === 200 && !str_contains($body, 'SECRET') && !str_contains($body, 'folder_root'), 'Configuration non exposée');
    [$status, $body] = request(['method' => 'GET', 'get' => ['operation' => 'list_local_backups'], 'bearer' => 'test-key']);
    $backups = json_decode($body, true)['backups'];
    check($status === 200 && count($backups) === 1 && $backups[0]['id'] === $archive, 'Liste sans liens, temp ou fichiers étrangers');
    check(request(['post' => ['operation' => 'list_local_backups', 'apipwd' => 'test-password']])[0] === 200, 'Liste via interface');
    [$status, $body] = request(['method' => 'GET', 'get' => ['operation' => 'download_local_backup', 'id' => $archive], 'bearer' => 'test-key']);
    check($status === 200 && $body === 'archive-content', 'Téléchargement exact');
    foreach (['../outside_2026-11-09__10-00-00.zip', '/etc/passwd', 'temp/backup.zip', 'notes.zip', 'link_2026-11-09__10-00-00.zip', "bad\0.zip"] as $id) {
        check(request(['method' => 'GET', 'get' => ['operation' => 'download_local_backup', 'id' => $id], 'bearer' => 'test-key'])[0] === 404, 'Chemin interdit : ' . json_encode($id));
    }
    check(request(['post' => ['operation' => ['load'], 'apipwd' => 'test-password']])[0] === 400, 'Paramètre tableau refusé');
    check(request(['post' => ['operation' => 'run_backup', 'group' => 'unknown', 'apipwd' => 'test-password']])[0] === 400, 'Groupe validé');
    foreach (['.', 'temp/..', '../archives', 'link_2026-11-09__10-00-00.zip'] as $path) {
        check(request(['post' => ['operation' => 'delete_item', 'group' => 'checks', 'task_name' => 'check',
            'type' => 'folder', 'path' => $path, 'apipwd' => 'test-password']])[0] === 404, 'Suppression dangereuse refusée');
    }
    check(request(['post' => ['operation' => 'delete_item', 'group' => 'checks', 'task_name' => 'check',
        'type' => 'anything', 'path' => $archive, 'apipwd' => 'test-password']])[0] === 400, 'Type de suppression validé');
    [$status, $body] = request(['post' => ['operation' => 'run_single_task', 'group' => 'checks', 'task_name' => 'check', 'apipwd' => 'test-password']]);
    check($status === 500 && !str_contains($body, 'SECRET'), 'Exception interne masquée');
    $before = glob(sys_get_temp_dir() . '/website-backup-sql-*');
    [$status, $body] = request(['post' => ['operation' => 'dump_and_download_db', 'item' => 'db', 'apipwd' => 'test-password']]);
    check($status === 200 && $body === 'SELECT 1;', 'Export SQL en POST');
    check($before === glob(sys_get_temp_dir() . '/website-backup-sql-*'), 'Export temporaire nettoyé');
    check(file_get_contents($fixture . '/archives/' . $archive) === 'archive-content', 'Archive préservée');
    echo $count . " vérifications réussies\n";
} finally {
    removeFixture($fixture);
}
