<?php

namespace App\Tools;

use Exception;

class CommandTools 
{

	public static function exec(string $cmd, string $errorMsg, &$output = [])
	{
		if (false === exec($cmd, $output))
		{
			throw new Exception($errorMsg . "\n" . implode("\n", $output ));
		}

	}

}
