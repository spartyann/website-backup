<?php

require_once(__DIR__ . '/vendor/autoload.php');

use App\Backup;
use App\Tools\PrintTools;
use Config\Config;

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
	define("IS_WIN", true);
} else {
	define("IS_WIN", false);
}

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


$apipwd = $_POST['apipwd'] ?? null;
$operation = $_POST['operation'] ?? null;
$group = $_POST['group'] ?? null;

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

	sendResponse($res);
}
else if ($operation == "run_backup")
{

	define("NL", "\n");
	define("VERBOSE", false);

	Backup::run($group);

	$data = PrintTools::getCache();

	sendResponse([
		'log' => $data
	]);
}


//sendError("Invalid operation");
