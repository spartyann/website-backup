<?php

namespace App\Tools;


class PrintTools 
{
	private static function isCommandLineInterface()
	{
		return (php_sapi_name() === 'cli');
	}

	public static function title1(string $text)
	{
		if (VERBOSE == false) return;

		echo NL;
		$line = "===== " . $text . " =====";
		echo str_repeat('=', strlen($line)) . NL;
		echo $line . NL;
		echo str_repeat('=', strlen($line)) . NL;
		echo NL;
	}

	public static function title2(string $text)
	{
		if (VERBOSE == false) return;

		echo NL;
		echo "##### " . $text . " #####" . NL . NL;
	}

	public static function title3(string $text)
	{
		if (VERBOSE == false) return;

		echo NL;
		echo "##### " . $text . NL . NL;
	}

	public static function text(string $text)
	{
		if (VERBOSE == false) return;

		echo $text . NL;
	}

}
