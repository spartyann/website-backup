<?php
// Vérifications HTTP locales et base réelle de démonstration, sans navigateur.
require_once __DIR__ . '/bootstrap.php';
$count = 0;
function check(bool $condition, string $message): void {
    global $count;
    if (!$condition) throw new RuntimeException($message);
    $count++;
}
function request(string $path, ?array $post = null, ?string $key = null): array {
    $curl = curl_init('http://127.0.0.1' . $path);
    curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 60,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => $key === null ? [] : ['Authorization: Bearer ' . $key]]);
    if ($post !== null) {
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body = curl_exec($curl);
    if ($body === false) throw new RuntimeException('Requête HTTP locale impossible');
    return [curl_getinfo($curl, CURLINFO_HTTP_CODE), $body];
}
function api(string $operation, array $params = []): array {
    return request('/api.php', array_merge($params, ['operation' => $operation, 'apipwd' => \Config\Config::UI_PASSWORD]));
}
check(request('/ui.php')[0] === 200, 'Interface inaccessible');
check(request('/assets/js/local_backups.vue')[0] === 200, 'Composant Vue inaccessible');
foreach (['/Config/Config.sample.php', '/Config/get_google_token.php', '/vendor/autoload.php', '/vendor/composer/installed.json'] as $path) {
    check(request($path)[0] === 403, 'Fichier privé accessible : ' . $path);
}
check(api('login')[0] === 200, 'Connexion impossible');
check(request('/api.php', ['operation' => 'login', 'apipwd' => 'incorrect'])[0] === 401, 'Mot de passe invalide accepté');
check(request('/api.php?operation=login&apipwd=incorrect')[0] === 400, 'Secret en URL accepté');
check(request('/api.php?operation=list_local_backups')[0] === 401, 'Liste publique');
check(request('/api.php', ['operation' => 'run_backup'], \Config\Config::LOCAL_BACKUP_API_KEY)[0] === 403, 'Droits Bearer excessifs');
[$status, $body] = api('load');
check($status === 200 && !str_contains($body, \WB_DEV_DB_PASSWORD), 'Configuration sensible exposée');
[$status, $body] = request('/api.php?operation=list_local_backups', null, \Config\Config::LOCAL_BACKUP_API_KEY);
$archives = json_decode($body, true)['backups'] ?? [];
check($status === 200 && $archives !== [], 'Archives de démonstration absentes');
$archive = $archives[0];
[$status, $body] = request('/api.php?' . http_build_query(['operation' => 'download_local_backup', 'id' => $archive['id']]),
    null, \Config\Config::LOCAL_BACKUP_API_KEY);
check($status === 200 && strlen($body) === $archive['size'], 'Téléchargement Bearer incorrect');
check(hash('sha256', $body) === hash_file('sha256', '/data/backups/' . $archive['name']), 'Archive téléchargée altérée');
[$status, $body] = api('download_local_backup', ['id' => $archive['id']]);
check($status === 200 && strlen($body) === $archive['size'], 'Téléchargement POST incorrect');
[$status, $body] = api('dump_and_download_db', ['item' => 'demo']);
check($status === 200 && str_contains($body, 'CREATE TABLE') && str_contains($body, 'demo_notes'), 'Export MariaDB incorrect');
[$status, $body] = api('run_backup', ['group' => 'demo_database']);
$result = json_decode($body, true);
check($status === 200 && ($result['success'] ?? false) && str_contains($result['log'] ?? '', 'Backup COMPLETE')
    && !str_contains($result['log'] ?? '', 'ERROR'), 'Sauvegarde MariaDB incorrecte');
[$status, $body] = api('run_single_task', ['group' => 'demo_integrity', 'task_name' => 'Vérifier les fichiers']);
$result = json_decode($body, true);
check($status === 200 && isset($result['result']['result']), 'Analyse d’intégrité impossible');
check(fileowner('/data/backups/' . $archive['name']) === (int) getenv('WB_DEV_UID'), 'UID des archives incorrect');
check(filegroup('/data/backups/' . $archive['name']) === (int) getenv('WB_DEV_GID'), 'GID des archives incorrect');
$databaseArchives = glob('/data/backups/demo_database_*');
check($databaseArchives !== [], 'Archive DB absente');
foreach ($databaseArchives as $file) {
    check(fileowner($file) === (int) getenv('WB_DEV_UID') && filegroup($file) === (int) getenv('WB_DEV_GID'),
        'UID/GID des archives créées par Apache incorrects');
}
echo $count . " vérifications HTTP/DB/droits réussies\n";
