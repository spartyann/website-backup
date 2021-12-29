<?php

use App\S3\S3Manager;

require_once(__DIR__ . '/vendor/autoload.php');


$files = S3Manager::getAllS3Files();

dd($files);


