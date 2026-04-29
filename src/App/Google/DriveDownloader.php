<?php

namespace App\Google;

use App\ExceptionWithCustomTrace;
use App\Tools\FileTools;
use DateTime;
use Google\Service\Drive;

class DriveDownloader 
{


	public static function download(
		string $mode,
		array $types_to_export,
		string $files_sync_dir,
		array $google_auth,
	) : array
	{

		$res = [];

		$files_sync_dir = rtrim($files_sync_dir, '/\\') . DIRECTORY_SEPARATOR;
		if (!is_dir($files_sync_dir)) mkdir($files_sync_dir, 0755, true);

		$existingFiles = self::getExistingFiles($files_sync_dir);

		//dd($existingFiles);

		$client = new \Google_Client();
		$client->setApplicationName('Google Sheets API');
		$client->setScopes([Drive::DRIVE_READONLY]);
		$client->setAccessType('offline');

		$client->setAuthConfig($google_auth);

		$service = new Drive($client);

		$conversions = [];
		
		if (in_array('document', $types_to_export)) {
			$conversions[] = [
				'type_mime' => 'application/vnd.google-apps.document',
				'mime'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'ext'   => '.docx'
			];
		}
		if (in_array('spreadsheet', $types_to_export)) {
			$conversions[] = [
				'type_mime' => 'application/vnd.google-apps.spreadsheet',
				'mime'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'ext'   => '.xlsx'
			];
		}
		if (in_array('presentation', $types_to_export)) {
			$conversions[] = [
				'type_mime' => 'application/vnd.google-apps.presentation',
				'mime'  => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
				'ext'   => '.pptx'
			];
		}

		$allFiles = [];

		foreach ($conversions as $conversion) {
			$mimeType = $conversion['type_mime'];

			/*$queryDate = '';
			if ($days_updated !== null && $days_updated > 0) {
				$dateLimite = (new DateTime())->modify('-' . $days_updated . ' days')->format('Y-m-d\TH:i:s\Z');
				$queryDate  = " and modifiedTime > '$dateLimite'";
			}*/

			$query = "mimeType='$mimeType' and trashed=false";
			//$query = "mimeType='$mimeType' and trashed=false" . $queryDate;
			//$query = "trashed=false";

			$pageToken = true;
			while ($pageToken) {

				$params = [
					'q'          => $query,
					'fields'     => 'nextPageToken, files(id, name, modifiedTime, mimeType)',
					'pageSize'   => 1000,
					'pageToken'  => $pageToken === true ? null : $pageToken
				];

				$results = $service->files->listFiles($params);
				$pageToken = $results->getNextPageToken();

				foreach ($results->getFiles() as $file) {
					$modifiedTime = new \DateTime($file->getModifiedTime());
					$modifiedTime = new \DateTime($modifiedTime->format('c'));

					$allFiles[$file->getId()] = [
						'id' => $file->getId(),
						'name' => $file->getName(),
						'modifiedTime' => $modifiedTime,
						'mimeType' => $file->getMimeType(),
						'ExportMime' => $conversion['mime'],
						'ExportExt' => $conversion['ext']
					];
				}
			}
		}

		foreach($allFiles as $file) {

			$existingFile = $existingFiles[$file['id']] ?? null;

			if ($existingFile) {
				// If file already exists, compare modified time to avoid re-downloading

				//dd($file['modifiedTime'], $existingFile['modifiedTime']);
				if ($file['modifiedTime'] <= $existingFile['modifiedTime']) {
					//echo "File already exists and is up to date: " . $file['name'] . "\n";
					continue;
				}
			}

			$dateString = $file['modifiedTime']->format('Y_m_d__H_i_s_O');
			$ext = $file['ExportExt'];

			$exportFileName = $file['id'] . ' - ' . $dateString . ' - ' . FileTools::cleanupFileChars($file['name']) . $ext;
			$exportPath = $files_sync_dir . $exportFileName;

			//echo $exportFileName . "\n";
			//continue;

			$response = $service->files->export(
				$file['id'],
				$file['ExportMime'],
				['alt' => 'media']
			);

			file_put_contents($exportPath, $response->getBody()->getContents());

			$res[] = 'Get ' . $ext . ': ' . $file['name'];

			if ($existingFile) {
				unlink($existingFile['path']);
			}
		}

		// Remove deleted files
		foreach ($existingFiles as $existingFile) {
			if (!isset($allFiles[$existingFile['id']])) {
				//echo "File deleted: " . $existingFile['name'] . "\n";
				unlink($existingFile['path']);
				$res[] = 'Del .' . $existingFile['ext'] . ': ' . $existingFile['name'];
			}
		}

		return $res;
	}


	public static function getExistingFiles(string $files_sync_dir) : array
	{
		$files_sync_dir = rtrim($files_sync_dir, '/\\') . DIRECTORY_SEPARATOR;

		if (!is_dir($files_sync_dir)) return [];

		$existingFiles = [];
		foreach (scandir($files_sync_dir) as $file) {
			if ($file === '.' || $file === '..') continue;

			preg_match('/^(.+) - (\d{4}_\d{2}_\d{2}__\d{2}_\d{2}_\d{2}_[0-9-+]+) - (.+)\.(.+)$/', $file, $matches);
			if (!$matches) continue;

			//dd($matches[2], DateTime::createFromFormat('Y_m_d__H_i_s_O', $matches[2]));

			$existingFiles[$matches[1]] = [
				'id' => $matches[1],
				'modifiedTime' => DateTime::createFromFormat('Y_m_d__H_i_s_O', $matches[2]),
				'name' => $matches[3],
				'ext' => $matches[4],
				'path' => $files_sync_dir . $file
			];
		}

		return $existingFiles;
	}

}
