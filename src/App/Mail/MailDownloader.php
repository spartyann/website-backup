<?php

namespace App\Mail;

use App\ExceptionWithCustomTrace;
use App\Tools\FileTools;
use DirectoryTree\ImapEngine\Enums\ImapFetchIdentifier;
use DirectoryTree\ImapEngine\Exceptions\ImapCommandException;
use DirectoryTree\ImapEngine\Exceptions\ImapConnectionException;
use DirectoryTree\ImapEngine\Mailbox;
class MailDownloader 
{

	public static function progressBar(int $current, int $total, int $width = 40): void
	{
		$percent  = $current / $total;
		$filled   = (int) round($percent * $width);
		$empty    = $width - $filled;

		$bar  = str_repeat('█', $filled) . str_repeat('░', $empty);
		$pct  = str_pad((int)($percent * 100), 3, ' ', STR_PAD_LEFT);

		echo "\r[{$bar}] {$pct}% ({$current}/{$total})";

		if ($current === $total) {
			echo PHP_EOL;
		}
	}


	public static function downloadMails(
		string $host,
		string $port,
		string $user,
		string $password,
		string $encryption,
		?array $mailBoxesNames,
		string $mailsSyncDir,
		?string $phpCommand,
		bool $optim_mem = false,
		bool $verbose = false
	)
	{

		if (is_dir($mailsSyncDir) == false) mkdir($mailsSyncDir);

		//https://imapengine.com/docs/usage/folders

		$args = [];
		$args[] = $host;
		$args[] = $port;
		$args[] = $user;
		$args[] = $password;
		$args[] = $encryption;
		$args[] = json_encode($mailBoxesNames);
		$args[] = $mailsSyncDir;
		$args[] = $verbose ? '1' : '0';
		$args[] = '1'; // Minimum Verbose

		if (PHP_OS_FAMILY === 'Windows') {
			$args = array_map(function($a) { return str_replace('!', '----EX----', $a); }, $args);
		}

		$args = array_map(function($a) { return escapeshellarg($a); }, $args);

		$php = $phpCommand ?? PHP_BINARY;

		if ($optim_mem) $script = escapeshellarg(__DIR__ . '/WorkerMailBox.php');
		else $script = escapeshellarg(__DIR__ . '/WorkerMailBoxMessages.php');
		
		$command = "{$php} {$script} " . implode(' ', $args);
		//$res = shell_exec($command);

		$resStream = self::execStream($command, $verbose, $args);

		$result = $resStream[1] ?? null;

		if ($resStream[0] !== 0) {
			echo "Error executing Worker. Exit code: {$resStream}\n";
			exit(1);
		}

		return $result;
	}


	private static function execStream(string $command, $verbose = false, array $env = []): array
	{
		$results = [];
		$handle = popen("{$command} 2>&1", 'r');

		if ($handle === false) {
			throw new \RuntimeException("Impossible d'ouvrir le process : {$command}");
		}

		while (!feof($handle)) {
			$line = fgets($handle);
			if ($line === false) continue;

			$data = json_decode(trim($line), true);

			// Ligne JSON valide
			if (json_last_error() === JSON_ERROR_NONE && isset($data['type'])) {
				if ($data['type'] === 'progress') {
					if ($verbose) self::progressBar(
						intval($data['current']),
						intval($data['total'])
					);
				} elseif ($data['type'] === 'log') {
					if ($verbose) {
						echo $data['message'] . "\n";
					}
				} elseif ($data['type'] === 'exception') {

					$ex = ExceptionWithCustomTrace::withTrace(
						$data['message'],
						$data['trace'] ?? []
					);

					throw $ex;
				} elseif ($data['type'] === 'result') {
					$results[] = $data['result'];
				}
				
			} else {
				// Ligne non-JSON (erreur PHP, exception...) → afficher en rouge
				//echo "\n\033[31m{$line}\033[0m";
				echo $line;
				flush(); // force l'affichage immédiat en CLI
			}
		}

		$exitCode = pclose($handle);
		return [$exitCode, $results];
	}

}
