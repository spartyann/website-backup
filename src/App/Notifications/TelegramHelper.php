<?php
namespace App\Notifications;

use Config\Config;

class TelegramHelper
{
	public static function escape(string $string)
	{
		//$string = str_replace('_', '\_', $string);
		$string = str_replace('*', '\*', $string);
		$string = str_replace('*', '\*', $string);
		$string = str_replace('~', '\~', $string);
		$string = str_replace('`', '\`', $string);
		$string = str_replace('[', '\[', $string);
		$string = str_replace(']', '\]', $string);
		$string = str_replace('(', '\(', $string);
		$string = str_replace(')', '\)', $string);
		
		return $string;
	}
	
	public static function ellipsis(string $string, int $max = 300)
	{
		if (strlen($string) > $max) return substr($string, 0, $max) . '[...]';
		return $string;
	}

	public static function sendMessage(string $title, string $msg, ?string $chatId = null, ?string $token = null){
		
		//Config::NOTIF_DISCORD_WEBHOOK_URL

		$chat_id = $chatId ?? Config::NOTIF_TELEGRAM_CHAT_ID;
		$token = $token ?? Config::NOTIF_TELEGRAM_TOKEN;
		
		$title = preg_replace('/[^A-Za-z0-9\-\_ ]/', '', $title);

		$title = self::ellipsis($title, 300);
		$msg = self::ellipsis($msg, 4096 - strlen($title) - 10); // 4096 is the max length of a Telegram message, we keep some margin for the title and formatting

		
		// L'URL de l'API Telegram
		$url = "https://api.telegram.org/bot" . $token . "/sendMessage";

		// Les données à envoyer
		$data = [
			'chat_id' => $chat_id,
			'text' => '*' . $title . "* \n\n " . $msg,
			'parse_mode' => 'Markdown', // Permet d'utiliser des balises HTML basiques comme <b>, <i>
			'disable_web_page_preview' => true // Désactive l'aperçu des liens (optionnel)
		];

		// Initialisation de cURL
		$ch = curl_init();
		
		// Configuration des options cURL
		curl_setopt_array($ch, [
			CURLOPT_URL => $url,
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => http_build_query($data), // Encodage des données
			CURLOPT_RETURNTRANSFER => true, // Retourne la réponse au lieu de l'afficher
			CURLOPT_SSL_VERIFYPEER => false // À mettre sur "true" en production si possible
		]);

		// Exécution de la requête
		$response = curl_exec($ch);
		
		// Gestion des erreurs cURL
		if(curl_errno($ch)) {
			$error = curl_error($ch);
			curl_close($ch);
			return "Erreur cURL : " . $error;
		}
		
		curl_close($ch);
		return $response;
	}


}