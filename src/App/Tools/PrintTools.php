<?php

namespace App\Tools;

use Config\Config;

class PrintTools 
{
	private static $cache = '';
	private static $NL = "\n";
	
	public static function defineNewLine(string $nl)
	{
		self::$NL = $nl;
	}

	public static function getCache(): string
	{
		return self::$cache;
	}

	private static function appendOutput(string $text)
	{
		self::$cache .= $text;
		if (Config::VERBOSE()) echo $text;
	}

	public static function title1(string $text)
	{
		$res = '';

		$res .=  self::$NL;
		$line = "===== " . $text . " =====";
		$res .=  str_repeat('=', strlen($line)) . self::$NL;
		$res .=  $line . self::$NL;
		$res .=  str_repeat('=', strlen($line)) . self::$NL;
		$res .=  self::$NL;

		self::appendOutput($res);
	}

	public static function title2(string $text)
	{
		$res = '';

		$res .=  self::$NL;
		$res .=  "##### " . $text . " #####" . self::$NL . self::$NL;

		self::appendOutput($res);
	}

	public static function title3(string $text)
	{
		$res = '';
		$res .=  self::$NL;
		$res .=  "##### " . $text . self::$NL . self::$NL;

		self::appendOutput($res);
	}

	public static function text(string $text)
	{
		$res = '';
		$res .=  $text . self::$NL;
		self::appendOutput($res);
	}

}
