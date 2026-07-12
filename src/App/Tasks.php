<?php

namespace App;

use App\Google\DriveDownloader;
use App\Google\SearchConsoleDownloader;
use App\IntegrityCheck\IntegrityChecker;
use App\Mail\MailDownloader;
use App\Notifications\DiscordHelper;
use App\Notifications\SlackHelper;
use App\Notifications\TelegramHelper;
use App\S3\S3Manager;
use App\Tools\CommandTools;
use App\Tools\FileTools;
use App\Tools\PrintTools;
use Config\Config;
use Exception;
use Druidfi\Mysqldump\Mysqldump;

class Tasks 
{
    public static function run(?string $onlyThisGroups)
	{
		$start = time();

		PrintTools::text("Starting Tasks" . ($onlyThisGroups == null ? '' : ('Group selected: ' . $onlyThisGroups)));

		$onlyThisGroupsList = explode(',', $onlyThisGroups ?? '');

		foreach(Config::tasks() as $groupName => $tasks)
		{
			if ($onlyThisGroups != null && in_array($groupName, $onlyThisGroupsList) == false) continue;

			PrintTools::title1("Task Group: " . $groupName);
			self::taskGroup($groupName, $tasks);
		}

		$end = time();

		PrintTools::text('');
		PrintTools::text("**** ALL Groups completed in " . ($end - $start) . 's');
		PrintTools::text('');

	}

	private static function taskGroup(string $groupName, array $tasks)
	{
		$start = time();
		try
		{
			$tmpDir = FileTools::prepareTempDir();
			$notifMessage = '';

			foreach($tasks as $task) {

				if ($task['task'] == 'integrity_check')
				{
					$taskName = $task['name'];
					$results = IntegrityChecker::check($task, $tmpDir);

					if (count($results) > 0) {
						PrintTools::text("Results for: " . json_encode($results, JSON_PRETTY_PRINT));
						$notifMessage .= "Integrity: $taskName\n- " . implode("\n- ", $results) . "\n\n";
					} else {
						$notifMessage .= "Integrity: $taskName\n- Nothing\n\n";
					}

				}
				else if ($task['task'] == 'integrity_build_inventory')
				{
					$taskName = $task['name'];
					$results = IntegrityChecker::buildInventory($task, $tmpDir);
					
					if (count($results) > 0) {
						PrintTools::text("Results for: " . json_encode($results, JSON_PRETTY_PRINT));
						$notifMessage .= "Inventory: $taskName\n- " . implode("\n- ", $results) . "\n\n";
					} else {
						$notifMessage .= "Inventory: $taskName\n- Nothing\n\n";
					}
				}
				else {
					throw new \Exception("Invalid item Task: " . $task['task']);
				}

			}

			$end = time();
			PrintTools::text("Tasks COMPLETE in " . ($end - $start) . "s !");

			$notifTitle = Config::NOTIF_TITLE ?? 'Tasks completed';
			$notifTitle .= " ($groupName)";

			// Notify
			if (Config::NOTIF_DISCORD_WEBHOOK_URL != null)
			{
				PrintTools::text("Sending discord notif");
				DiscordHelper::sendMessage($notifTitle, DiscordHelper::escape($notifMessage), '#00ff00');
			}

			// Notify
			if (Config::NOTIF_SLACK_WEBHOOK_URL != null)
			{
				PrintTools::text("Sending Slack notif");
				SlackHelper::sendMessage($notifTitle, SlackHelper::escape($notifMessage));
			}

			// Notify
			if (Config::NOTIF_TELEGRAM_CHAT_ID != null)
			{
				PrintTools::text("Sending Telegram notif");
				TelegramHelper::sendMessage($notifTitle, TelegramHelper::escape($notifMessage));
			}

		}
		catch (\Throwable $ex)
		{
			$msg = $ex->getMessage();
			$msg .= "\n" . $ex->getTraceAsString();

			PrintTools::title2("ERROR");
			PrintTools::text($msg);

			$notifTitle = Config::NOTIF_ERROR_TITLE ?? 'Tasks ERROR';
			$notifTitle .= " ($groupName)";

			// Notify
			if (Config::NOTIF_ERROR_DISCORD_WEBHOOK_URL != null)
			{
				DiscordHelper::sendMessage($notifTitle, DiscordHelper::escape($msg), '#ff0000', Config::NOTIF_ERROR_DISCORD_WEBHOOK_URL);
			}

			if (Config::NOTIF_ERROR_SLACK_WEBHOOK_URL != null)
			{
				SlackHelper::sendMessage($notifTitle, SlackHelper::escape($msg), Config::NOTIF_ERROR_SLACK_WEBHOOK_URL);
			}

			if (Config::NOTIF_ERROR_TELEGRAM_CHAT_ID != null)
			{
				PrintTools::text("Sending Telegram notif");
				TelegramHelper::sendMessage($notifTitle, TelegramHelper::escape($msg),
					Config::NOTIF_ERROR_TELEGRAM_CHAT_ID, Config::NOTIF_ERROR_TELEGRAM_TOKEN);
			}

			if (Config::DEBUG) throw $ex;
		}

	}


}
