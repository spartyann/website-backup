<?php

namespace App\IntegrityCheck\Support;

use PDO;

class DbConnector
{

	public static function connect(array $task): PDO
	{
		$dsn = "mysql:host={$task['db_host']};port={$task['db_port']};dbname={$task['db_name']};charset=utf8mb4";

		return new PDO($dsn, $task['db_user'], $task['db_pwd'], [
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
		]);
	}

}
