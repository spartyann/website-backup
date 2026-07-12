<?php

require_once(__DIR__ . '/vendor/autoload.php');

use App\Backup;
use App\Tasks;
use App\Tools\FileTools;
use App\Tools\PrintTools;
use Config\Config;

function sendResponse($response){
	header('Content-Type: application/json');

	if (is_string($response))
	{
		$response = [ 'message' => $response ];
	}
	$response ['success'] = true;

	echo json_encode($response);
	exit(0);
}

function sendError(string $response){
	header('Content-Type: application/json');

	echo json_encode([ 'success' => false, 'message' => $response ]);
	exit(0);
}

function apiParam($name, $defaultValue = null){
	return $_POST[$name] ?? ($_GET[$name] ?? $defaultValue);
}

// Résout $relativePath par rapport à $folderRoot et vérifie qu'il ne s'en échappe pas (path traversal).
// Retourne le chemin absolu réel si valide, ou null sinon.
function resolveSafePath(string $folderRoot, string $relativePath): ?string {
	$root = realpath($folderRoot);
	if ($root === false) return null;
	$root = str_replace('\\', '/', $root);

	$relativePath = trim(str_replace('\\', '/', $relativePath), '/');
	if ($relativePath === '') return null; // interdit de cibler la racine du site elle-même

	$target = realpath($root . '/' . $relativePath);
	if ($target === false) return null; // n'existe pas

	$target = str_replace('\\', '/', $target);
	if ($target !== $root && str_starts_with($target, $root . '/') == false) return null; // hors du dossier autorisé

	return $target;
}

function findTask(string $groupName, string $taskName): ?array {
	$tasks = Config::tasks()[$groupName] ?? null;
	if ($tasks === null) return null;

	foreach ($tasks as $t) if (($t['name'] ?? null) === $taskName) return $t;

	return null;
}


$apipwd = apiParam('apipwd');
$operation = apiParam('operation');
$group = apiParam('group');
$item = apiParam('item');
$taskName = apiParam('task_name');

if ($apipwd != Config::UI_PASSWORD)
{
	sendError("Invalid password");
}


if ($operation == "login")
{
	sendResponse("ok");

}
else if ($operation == "load")
{
	$res = [];

	$groups = json_decode(json_encode(Config::groups()));
	
	foreach($groups as $gKey => &$group)
	{
		$group->name = $gKey;
		foreach($group->items as $iKey => &$item)
		{
			if (isset($item->pwd))	unset($item->pwd);
			if (isset($item->password))	unset($item->password);
		}
	}

	$res['groups'] = $groups;

	$taskGroups = [];
	foreach (Config::tasks() as $gKey => $tasks)
	{
		$items = [];
		foreach ($tasks as $t)
		{
			$items[] = [
				'name' => $t['name'] ?? '',
				'task' => $t['task'] ?? '',
				'integrity_type' => $t['integrity_type'] ?? null,
			];
		}
		$taskGroups[] = [ 'name' => $gKey, 'items' => $items ];
	}

	$res['taskGroups'] = $taskGroups;

	sendResponse($res);
}
else if ($operation == "run_backup")
{
	Backup::run($group);

	$data = PrintTools::getCache();

	sendResponse([
		'log' => $data
	]);
}
else if ($operation == "dump_and_download_db")
{

	$tempDir = FileTools::prepareTempDir();
	$fileDb = $tempDir . '/dumptemp.sql';


	$itemDB = Config::downloadDBApi()[$item] ?? null;

	if ($itemDB == null) sendError("Invalid DB item");
	
	$notifMessage = '';
	Backup::dumpDb($fileDb, Config::downloadDBApi()[$item], $notifMessage);

	if (file_exists($fileDb) == false)
	{
		sendError("Dump file not created");
	}

	header('Content-Description: File Transfer');
	header('Content-Type: application/sql');
	header('Content-Disposition: attachment; filename="dump.sql"');
	header('Expires: 0');
	header('Cache-Control: must-revalidate');
	header('Pragma: public');
	header('Content-Length: ' . filesize($fileDb));
	readfile($fileDb);

	FileTools::prepareTempDir();
}
else if ($operation == "run_tasks")
{
	Tasks::run($group);

	$data = PrintTools::getCache();

	sendResponse([
		'log' => $data
	]);
}
else if ($operation == "run_single_task")
{
	try
	{
		$result = Tasks::runSingleTask($group, $taskName);
	}
	catch (\Throwable $ex)
	{
		sendError($ex->getMessage());
	}

	sendResponse([
		'result' => $result
	]);
}
else if ($operation == "delete_item")
{
	$path = apiParam('path');
	$type = apiParam('type'); // 'file' | 'folder'

	$task = findTask($group, $taskName);
	if ($task == null) sendError("Tâche introuvable");
	if (empty($task['folder_root'])) sendError("Cette tâche n'a pas de dossier associé");
	if ($path == null || $path == '') sendError("Chemin manquant");

	$safePath = resolveSafePath($task['folder_root'], $path);
	if ($safePath == null) sendError("Chemin invalide ou introuvable");

	if ($type == 'folder')
	{
		if (is_dir($safePath) == false) sendError("Dossier introuvable");
		FileTools::removeDir($safePath);
	}
	else
	{
		if (is_file($safePath) == false) sendError("Fichier introuvable");
		unlink($safePath);
	}

	sendResponse("Supprimé");
}


//sendError("Invalid operation");
