<?php

namespace App\Tools;

class Tools 
{
	
	public static function isWindows(): bool
	{	
		if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
			return true;
		} else {
			return false;
		}
	}

	public static function isCommandLineInterface(): bool
	{
		return (php_sapi_name() === 'cli');
	}

}
