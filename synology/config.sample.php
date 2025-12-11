<?php


define('CONF', [
	[
		'enabled' => false,
		'url' => 'xxxxxxxxxxxxxxxxxxx',
		'type' => 'dump_sql',

		'db_host' =>  'xxxxxxxx',
		'db_user' =>  'xxxxxxx',
		'db_pwd' =>  'xxxxxxxxx',
		'db_db' =>  'xxxxxxxx',
	],

	[
		//
		'enabled' => true,
		'url' => 'https://xxxxxxxxx/index.php?'. http_build_query([
			'module'=>'API',
			'method'=>'Actions.getPageUrls',
			'format'=>'JSON',
			'period'     => 'month',
    		'date'       => 'last3',
			'token_auth' => 'xxxxxxxxxxx',
			'idSite'	=> '1'
		]),

		'type' => 'matomo_json',

		'db_host' =>  'xxxxx',
		'db_user' =>  'xxxx',
		'db_pwd' =>  'xxxxx',
		'db_db' =>  'xxxxx',
		
	]

]);

