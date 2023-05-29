<?php
namespace App\Notifications;

use Config\Config;

class SlackHelper
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

	public static function sendMessage(string $title, string $msg, string $hookUrl = null)
	{
		$title = self::ellipsis($title, 300);
		$msg = self::ellipsis($msg, 8000);
		
		$ch = curl_init( $hookUrl == null ? Config::NOTIF_SLACK_WEBHOOK_URL : $hookUrl  );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
		curl_setopt( $ch, CURLOPT_POST, 1);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode([
				"text" => '*' . $title . "* \n\n " . $msg
		]));
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt( $ch, CURLOPT_HEADER, 0);
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
		
		$response = curl_exec( $ch );

		curl_close( $ch );
	}

}

/*
POST https://hooks.slack.com/services/T00000000/B00000000/XXXXXXXXXXXXXXXXXXXXXXXX
Content-type: application/json
{
    "text": "Danny Torrence left a 1 star review for your property.",
    "blocks": [
    	{
    		"type": "section",
    		"text": {
    			"type": "mrkdwn",
    			"text": "Danny Torrence left the following review for your property:"
    		}
    	},
    	{
    		"type": "section",
    		"block_id": "section567",
    		"text": {
    			"type": "mrkdwn",
    			"text": "<https://example.com|Overlook Hotel> \n :star: \n Doors had too many axe holes, guest in room 237 was far too rowdy, whole place felt stuck in the 1920s."
    		},
    		"accessory": {
    			"type": "image",
    			"image_url": "https://is5-ssl.mzstatic.com/image/thumb/Purple3/v4/d3/72/5c/d3725c8f-c642-5d69-1904-aa36e4297885/source/256x256bb.jpg",
    			"alt_text": "Haunted hotel image"
    		}
    	},
    	{
    		"type": "section",
    		"block_id": "section789",
    		"fields": [
    			{
    				"type": "mrkdwn",
    				"text": "*Average Rating*\n1.0"
    			}
    		]
    	}
    ]
}

*/
