<?php

ini_set('display_errors', '0');
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
require_once(__DIR__ . '/vendor/autoload.php');

use App\Backup;
use App\LocalBackups;
use App\Tasks;
use App\Tools\FileTools;
use App\Tools\PrintTools;
use Config\Config;

function sendResponse($response): never {
    header('Content-Type: application/json; charset=utf-8');
    if (is_string($response)) $response = ['message' => $response];
    $response['success'] = true;
    echo json_encode($response, JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function sendError(string $response, int $status = 400): never {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => false, 'message' => $response]);
    exit;
}

function apiParam(string $name, ?string $defaultValue = null): ?string {
    $value = $_POST[$name] ?? $_GET[$name] ?? $defaultValue;
    if ($value !== null && (!is_string($value) || strlen($value) > 4096)) {
        sendError('Paramètre invalide : ' . $name);
    }
    return $value;
}

function requiredParam(string $name): string {
    $value = apiParam($name);
    if ($value === null || $value === '') sendError('Paramètre manquant : ' . $name);
    return $value;
}

function resolveSafePath(string $folderRoot, string $relativePath): ?string {
    $root = realpath($folderRoot);
    if ($root === false) return null;
    $relativePath = str_replace('\\', '/', $relativePath);
    $parts = explode('/', $relativePath);
    $candidate = $root;
    foreach ($parts as $part) {
        if ($part === '' || $part === '.' || $part === '..' || str_contains($part, "\0")) return null;
        $candidate .= DIRECTORY_SEPARATOR . $part;
        if (is_link($candidate)) return null;
    }
    $target = realpath($candidate);
    if ($target === false || $target === $root) return null;
    return str_starts_with($target, rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR) ? $target : null;
}

function findTask(string $groupName, string $taskName): ?array {
    foreach (Config::tasks()[$groupName] ?? [] as $task) {
        if (($task['name'] ?? null) === $taskName) return $task;
    }
    return null;
}

function validateGroups(?string $groups, array $configured): void {
    if ($groups === null) return; // Tous les groupes, comme en CLI.
    foreach (explode(',', $groups) as $group) {
        if ($group === '' || !array_key_exists($group, $configured)) sendError('Groupe inconnu');
    }
}

function streamFile($file, string $name, string $contentType): void {
    $stat = fstat($file);
    if ($stat === false) throw new RuntimeException('File unavailable');
    header('Content-Type: ' . $contentType);
    header("Content-Disposition: attachment; filename=\"download." . pathinfo($name, PATHINFO_EXTENSION)
        . "\"; filename*=UTF-8''" . rawurlencode($name));
    header('Content-Length: ' . $stat['size']);
    fpassthru($file);
}

try {
    // Aucun secret n'est accepté dans l'URL, même si un mot de passe POST est présent.
    if (array_key_exists('apipwd', $_GET)) sendError('Le mot de passe doit être envoyé en POST');
    $operation = requiredParam('operation');
    $archiveOperations = ['list_local_backups', 'download_local_backup'];
    $operations = array_merge($archiveOperations, ['login', 'load', 'run_backup', 'dump_and_download_db',
        'run_tasks', 'run_single_task', 'delete_item']);
    if (!in_array($operation, $operations, true)) sendError('Opération inconnue');

    $method = $_SERVER['REQUEST_METHOD'] ?? '';
    $archiveOperation = in_array($operation, $archiveOperations, true);
    if ($method !== 'POST' && !($method === 'GET' && $archiveOperation)) {
        header('Allow: ' . ($archiveOperation ? 'GET, POST' : 'POST'));
        sendError('Méthode HTTP non autorisée', 405);
    }

    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($authorization !== '') {
        $key = defined(Config::class . '::LOCAL_BACKUP_API_KEY') ? Config::LOCAL_BACKUP_API_KEY : '';
        if (!is_string($key) || $key === '' || !preg_match('/^Bearer ([^\s]+)$/D', $authorization, $match)
            || !hash_equals($key, $match[1])) sendError('Authentification invalide', 401);
        if (!$archiveOperation) sendError('Clé limitée aux archives locales', 403);
    } else {
        $password = $_POST['apipwd'] ?? null;
        if (!is_string($password) || $password === '' || strlen($password) > 4096
            || !is_string(Config::UI_PASSWORD) || Config::UI_PASSWORD === ''
            || !hash_equals(Config::UI_PASSWORD, $password)) sendError('Authentification invalide', 401);
        if ($method !== 'POST') sendError('Authentification POST requise', 405);
    }

    if ($operation === 'login') sendResponse('ok');

    if ($operation === 'load') {
        // L'interface a uniquement besoin des noms : aucune configuration sensible exposée.
        $groups = [];
        foreach (Config::groups() as $name => $group) $groups[] = ['name' => (string) $name];
        $taskGroups = [];
        foreach (Config::tasks() as $name => $tasks) {
            $items = [];
            foreach ($tasks as $task) $items[] = ['name' => $task['name'] ?? '', 'task' => $task['task'] ?? '',
                'integrity_type' => $task['integrity_type'] ?? null];
            $taskGroups[] = ['name' => (string) $name, 'items' => $items];
        }
        sendResponse(['groups' => $groups, 'taskGroups' => $taskGroups]);
    }

    if ($archiveOperation) {
        $archives = new LocalBackups(Config::localStorageBackupDir());
        if ($operation === 'list_local_backups') sendResponse(['backups' => $archives->list()]);
        $id = requiredParam('id');
        $file = $archives->open($id);
        if ($file === null) sendError('Archive introuvable', 404);
        try { streamFile($file, $id, 'application/octet-stream'); }
        finally { fclose($file); }
        exit;
    }

    if ($operation === 'run_backup' || $operation === 'run_tasks') {
        $group = apiParam('group');
        validateGroups($group, $operation === 'run_backup' ? Config::groups() : Config::tasks());
        if ($operation === 'run_backup') Backup::run($group);
        else Tasks::run($group);
        sendResponse(['log' => PrintTools::getCache()]);
    }

    if ($operation === 'dump_and_download_db') {
        $item = requiredParam('item');
        $itemDB = Config::downloadDBApi()[$item] ?? null;
        if ($itemDB === null) sendError('Élément SQL inconnu', 404);
        // Chaque export possède son propre fichier privé, indépendant des sauvegardes en cours.
        $fileDb = tempnam(sys_get_temp_dir(), 'website-backup-sql-');
        if ($fileDb === false) throw new RuntimeException('Temporary file unavailable');
        try {
            if (!chmod($fileDb, 0600)) throw new RuntimeException('Temporary file permissions unavailable');
            $notification = '';
            Backup::dumpDb($fileDb, $itemDB, $notification);
            clearstatcache(true, $fileDb);
            if (!is_file($fileDb) || filesize($fileDb) === 0) throw new RuntimeException('Empty SQL export');
            $file = fopen($fileDb, 'rb');
            if ($file === false) throw new RuntimeException('SQL export unavailable');
            try { streamFile($file, 'dump.sql', 'application/sql'); }
            finally { fclose($file); }
        } finally { unlink($fileDb); }
        exit;
    }

    $group = requiredParam('group');
    $taskName = requiredParam('task_name');
    $task = findTask($group, $taskName);
    if ($task === null) sendError('Tâche introuvable', 404);
    if ($operation === 'run_single_task') {
        sendResponse(['result' => Tasks::runSingleTask($group, $taskName)]);
    }

    if (empty($task['folder_root'])) sendError("Cette tâche n'a pas de dossier associé");
    $path = requiredParam('path');
    $type = requiredParam('type');
    if (!in_array($type, ['file', 'folder'], true)) sendError('Type invalide');
    $safePath = resolveSafePath($task['folder_root'], $path);
    if ($safePath === null) sendError('Chemin invalide ou introuvable', 404);
    if ($type === 'folder') {
        if (!is_dir($safePath)) sendError('Dossier introuvable', 404);
        FileTools::removeDir($safePath);
        if (file_exists($safePath)) throw new RuntimeException('Directory deletion failed');
    } else {
        if (!is_file($safePath)) sendError('Fichier introuvable', 404);
        if (!unlink($safePath)) throw new RuntimeException('File deletion failed');
    }
    sendResponse('Supprimé');
} catch (Throwable $exception) {
    // Ne jamais renvoyer les chemins, commandes ou identifiants contenus dans les exceptions.
    error_log('website-backup API error: ' . get_class($exception));
    sendError('Une erreur interne est survenue', 500);
}
