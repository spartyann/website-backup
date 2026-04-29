<?php

require_once(__DIR__ . '/vendor/autoload.php');

use App\Backup;
use App\Tools\PrintTools;
use App\Tools\Tools;
use Config\Config;

if (Tools::isCommandLineInterface())
{
	$options = getopt('g:', ['verbose::']);
	$options['g'] = $options['g'] ?? null;
	$options['verbose'] = array_key_exists('verbose', $options);
}
else
{
	$options = array_merge($_GET, $_POST);
	
	$options['g'] = $options['g'] ?? null;
	$options['token'] = $options['token'] ?? null;
	$options['br'] = $options['br'] ?? '1';
	$options['verbose'] = array_key_exists('verbose', $options) && (($options['verbose'] ?? false) == true);

	if ($options['br'] == '0') PrintTools::defineNewLine("\n"); else PrintTools::defineNewLine("<br />");

	if (Config::URL_TOKEN != $options['token'])
	{
		echo "Bad token";
		exit(1);
	}	
}

Config::DEFINE_VERBOSE($options['verbose'] ?? null);

Backup::run($options['g']);
