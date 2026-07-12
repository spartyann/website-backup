<?php

namespace App\IntegrityCheck;

use App\ExceptionWithCustomTrace;
use App\Tools\FileTools;
use DateTime;

class IntegrityChecker 
{

	public static function check(array $task, string $tmpDir) : array
	{
		/* Liste de string pour affichage de résultat. Exemple:
			[ "New files:", "- file1", "- file2", "modified:", "- file1", "- file2" .... ]
		*/
		$res = []; 

		$res = [ "New files:", "- file1", "- file2", "modified:", "- file1", "- file2" ];
		
		// Code de vérification ICI



		return $res;
	}

}
