<?php

require_once(__DIR__ . '/vendor/autoload.php');

use App\Backup;

if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
	define("IS_WIN", true);
} else {
	define("IS_WIN", false);
}

Backup::run();

