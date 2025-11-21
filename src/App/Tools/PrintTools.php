<?php

namespace App\Tools;


class PrintTools 
{
	private static $cache = '';

	public static function getCache(): string
	{
		return self::$cache;
	}

	private static function appendOutput(string $text)
	{
		self::$cache .= $text;
		if (VERBOSE) echo $text;
	}

	private static function isCommandLineInterface()
	{
		return (php_sapi_name() === 'cli');
	}

	public static function title1(string $text)
	{
		$res = '';

		$res .=  NL;
		$line = "===== " . $text . " =====";
		$res .=  str_repeat('=', strlen($line)) . NL;
		$res .=  $line . NL;
		$res .=  str_repeat('=', strlen($line)) . NL;
		$res .=  NL;

		self::appendOutput($res);
	}

	public static function title2(string $text)
	{
		$res = '';

		$res .=  NL;
		$res .=  "##### " . $text . " #####" . NL . NL;

		self::appendOutput($res);
	}

	public static function title3(string $text)
	{
		$res = '';
		$res .=  NL;
		$res .=  "##### " . $text . NL . NL;

		self::appendOutput($res);
	}

	public static function text(string $text)
	{
		$res = '';
		$res .=  $text . NL;
		self::appendOutput($res);
	}

}
