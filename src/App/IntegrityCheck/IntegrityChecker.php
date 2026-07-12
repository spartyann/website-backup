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
		$result_strings = []; 
		
		$added_files = []; // nouveaux fichiers ajoutés aux sites
		$updated_files = []; // fichiers modifiés
		$missing_files = []; // fichiers manquants
		$result = ''; // OK|KO

		$database_items_found = []; // liste des lignes par Tables potentiellement compromises. 

		// $task est la même chose quand dans Config.php

		if ($task['integrity_type'] == 'database')
		{
			// Code de vérification ICI
		}
		else if ($task['integrity_type'] == 'joomla')
		{
			// Code de vérification ICI
		}
		else if ($task['integrity_type'] == 'generic')
		{
			// Code de vérification ICI
		}


		return [
			'result' => $result, // 
			'result_strings' => $result_strings,
			
			'added_files' => $added_files,
			'updated_files' => $updated_files,
			'missing_files' => $missing_files,

			'database_items_found' => $database_items_found
		];
	}

}
