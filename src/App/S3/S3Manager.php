<?php

namespace App\S3;

use App\Tools\FileTools;
use Aws\AwsClientInterface;
use Aws\Credentials\Credentials;
use Aws\S3\S3Client;
use Aws\Sdk;
use Config\Config;

class S3Manager 
{

	public static function getS3Client(): AwsClientInterface
	{
		$params = [
			'version' => '2006-03-01',
			'region' => Config::S3_REGION,
			'credentials' => [
				'key' => Config::S3_ACCESS_KEY_ID,
				'secret' => Config::S3_SECRET_ACCESS_KEY
			],
			// Set the S3 class to use objects.dreamhost.com/bucket
			// instead of bucket.objects.dreamhost.com
			'use_path_style_endpoint' => true
		];

		if (Config::S3_ENDPOINT != null && Config::S3_ENDPOINT != '') $params['endpoint'] = Config::S3_ENDPOINT;

		$s3Client = new S3Client($params);
	
		return $s3Client;
	}


	public static function getAllS3Files()
	{
		//if (Config::DEBUG) return json_decode(file_get_contents(__DIR__ . '/sample.json'), true);

		$s3Client = self::getS3Client();

		$contents = null;
		$lastKey = null;
		
		while ($contents === null || count($contents) > 0)
		{
			$res = $s3Client->listObjectsV2([
				'Bucket' => Config::S3_BUCKET,
				'StartAfter' => $lastKey,
				'Prefix' => FileTools::cleanAndCompleteDirPath(Config::S3_DIR)
			]);

			$contents = $res->get('Contents');

			// No content
			if ($contents === null) break;

			foreach($contents as $content)
			{
				//$files[$content['Key']] = $content;
				$files[] = [
					'file' => $content['Key'],
					'size' => intval($content['Size']),
					'rsize' => FileTools::getReadableSize($content['Size'], 2)
				];
				$lastKey = $content['Key'];
			}

			if (count($contents) < 1000) break;
		}
	

		//if (Config::DEBUG) file_put_contents(__DIR__ . '/sample.json', json_encode($files));


		return $files;
	}



	public static function download(string $key, string $saveAs)
	{
		$s3Client = self::getS3Client();

		$s3Client->getObject([
			'Bucket' => Config::S3_BUCKET,
			'Key' =>  $key,
			'SaveAs' => $saveAs
		]);
	}


	public static function put(string $key, string $file)
	{
		$s3Client = self::getS3Client();

		$s3Client->putObject([
			'Bucket' => Config::S3_BUCKET,
			'Key' => $key,
			'SourceFile' => $file
		]);
	}

	public static function delete(string $key)
	{
		$s3Client = self::getS3Client();

		$s3Client->deleteObject([
			'Bucket' => Config::S3_BUCKET,
			'Key' => $key
		]);
	}

}
