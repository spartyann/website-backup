<?php

require_once(__DIR__ . '/vendor/autoload.php');

use App\Backup;
use Config\Config;

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
	define("IS_WIN", true);
} else {
	define("IS_WIN", false);
}

function isCommandLineInterface()
{
    return (php_sapi_name() === 'cli');
}

if (isCommandLineInterface())
{
	$options = getopt('g:', ['verbose::']);
	$options['g'] = $options['g'] ?? null;
	$options['verbose'] = array_key_exists('verbose', $options);

	define("NL", "\n");
}
else
{
	$options = array_merge($_GET, $_POST);
	
	$options['g'] = $options['g'] ?? null;
	$options['token'] = $options['token'] ?? null;
	$options['br'] = $options['br'] ?? '1';
	$options['verbose'] = array_key_exists('verbose', $options) && (($options['verbose'] ?? false) == true);

	if ($options['br'] == '0') define("NL", "\n"); else define("NL", "<br />");

	if (Config::URL_TOKEN != $options['token'])
	{
		echo "Bad token";
		exit(1);
	}	
}

define("VERBOSE", $options['verbose']);

Backup::run($options['g']);
