<?php
require_once(__DIR__ . '/vendor/autoload.php');


use App\S3\S3Manager;


$files = S3Manager::getAllS3Files();

dd($files);

