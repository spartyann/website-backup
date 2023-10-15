<?php
namespace App\Notifications;

use Config\Config;

/**
 * DiscordHelper
 *
 */
class DiscordHelper
{

	public static function escape(string $string)
	{
		$string = str_replace('_', '\_', $string);
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

	public static function sendMessage(string $title, string $msg, string $colorHex = null, string $hookUrl = null)
	{
		$title = preg_replace('/[^A-Za-z0-9\-\_ ]/', '', $title);

		$title = self::ellipsis($title, 200);

		// Truncate to 2000

		$json_data = json_encode([
				"username"=> Config::NOTIF_DISCORD_USERNAME,
				"content"=> '',
				"embeds"=> [
				[
					"title" => $title,
					"description" => $msg,
					"color" => $colorHex == null ? null : hexdec($colorHex),
				]
			]
		]);
	
		$ch = curl_init( $hookUrl == null ? Config::NOTIF_DISCORD_WEBHOOK_URL : $hookUrl );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
		curl_setopt( $ch, CURLOPT_POST, 1);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $json_data);
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt( $ch, CURLOPT_HEADER, 0);
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
		
		$response = curl_exec( $ch );
		// If you need to debug, or find out why you can't send message uncomment line below, and execute script.
		// echo $response;
		curl_close( $ch );

	}

}

/*

FULL EXAMPLE
https://gist.github.com/Birdie0/78ee79402a4301b1faf412ab5f1cdcf9

===> See image discord_example.png


Color: https://www.binaryhexconverter.com/hex-to-decimal-converter

{
  "username": "Webhook",
  "avatar_url": "https://i.imgur.com/4M34hi2.png",
  "content": "Text message. Up to 2000 characters.",
  "embeds": [
    {
      "author": {
        "name": "Birdie♫",
        "url": "https://www.reddit.com/r/cats/",
        "icon_url": "https://i.imgur.com/R66g1Pe.jpg"
      },
      "title": "Title",
      "url": "https://google.com/",
      "description": "Text message. You can use Markdown here. *Italic* **bold** __underline__ ~~strikeout~~ [hyperlink](https://google.com) `code`",
      "color": 15258703,
      "fields": [
        {
          "name": "Text",
          "value": "More text",
          "inline": true
        },
        {
          "name": "Even more text",
          "value": "Yup",
          "inline": true
        },
        {
          "name": "Use `\"inline\": true` parameter, if you want to display fields in the same line.",
          "value": "okay..."
        },
        {
          "name": "Thanks!",
          "value": "You're welcome :wink:"
        }
      ],
      "thumbnail": {
        "url": "https://upload.wikimedia.org/wikipedia/commons/3/38/4-Nature-Wallpapers-2014-1_ukaavUI.jpg"
      },
      "image": {
        "url": "https://upload.wikimedia.org/wikipedia/commons/5/5a/A_picture_from_China_every_day_108.jpg"
      },
      "footer": {
        "text": "Woah! So cool! :smirk:",
        "icon_url": "https://i.imgur.com/fKL31aD.jpg"
      }
    }
  ]
}

*/
