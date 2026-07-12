<?php

namespace App\IntegrityCheck;

use App\IntegrityCheck\Database\DatabaseChecker;
use App\IntegrityCheck\Generic\GenericChecker;
use App\IntegrityCheck\Joomla\JoomlaChecker;
use Exception;

class IntegrityChecker
{

	public static function check(array $task, string $tmpDir) : array
	{
		// $task est la même chose que dans Config.php

		if ($task['integrity_type'] == 'database')
		{
			return DatabaseChecker::check($task, $tmpDir);
		}
		else if ($task['integrity_type'] == 'joomla')
		{
			return JoomlaChecker::check($task, $tmpDir);
		}
		else if ($task['integrity_type'] == 'generic')
		{
			return GenericChecker::check($task, $tmpDir);
		}

		throw new Exception("Invalid integrity_type: " . $task['integrity_type']);
	}

	public static function buildInventory(array $task, string $tmpDir)
	{
		return GenericChecker::buildInventory($task, $tmpDir);
	}


}
