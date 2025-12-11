<?php

require_once __DIR__ . '/config.php';


function downloadFileWithCurl($url, $destination) {
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => false,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT => 300,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0'
    ]);
    
    $file = fopen($destination, 'wb');
    curl_setopt($ch, CURLOPT_FILE, $file);
    
    $result = curl_exec($ch);
    
    if ($result === false) {
        $error = curl_error($ch);
        fclose($file);

        return false;
    }
    
    fclose($file);
    return true;
}


function importViaMysqlCommand($sqlFile, $host, $user, $pass, $database) {
    $command = sprintf(
        'mysql -h %s -u %s -p%s %s < %s',
        escapeshellarg($host),
        escapeshellarg($user),
        escapeshellarg($pass),
        escapeshellarg($database),
        escapeshellarg($sqlFile)
    );
    
    $output = null;
    $returnVar = null;
    
    exec($command, $output, $returnVar);
    
    if ($returnVar === 0) {
        echo "Import réussi";
        return true;
    } else {
        echo "Erreur lors de l'import";
        echo implode("\n", $output);
        return false;
    }
}




foreach (CONF as $conf) {
	if ($conf['enabled'] === false) continue;



	if ($conf['type'] === 'dump_sql' ) {

		$file = __DIR__ . '/dump.sql';

		if (file_exists($file)) {
			unlink($file);
		}

		if (downloadFileWithCurl($conf['url'], $file) == false){
			echo "Error downloading file from URL \n";
			exit(1);
		}

		if (importViaMysqlCommand(
			$file,
			$conf['db_host'],
			$conf['db_user'],
			$conf['db_pwd'],
			$conf['db_db']
		) == false) {
			echo "Error importing file from URL \n";
			exit(1);
		}

	} else if ($conf['type'] === 'matomo_json' ) {

		$file = __DIR__ . '/dump.json';

		if (file_exists($file)) {
			unlink($file);
		}

		if (downloadFileWithCurl($conf['url'], $file) == false){
			echo "Error downloading file from URL \n";
			exit(1);
		}

		file_put_contents($file, json_encode(json_decode(file_get_contents($file)), JSON_PRETTY_PRINT));


		// TODO

		
	} else {
		echo "Type not managed \n";
		exit(1);
	}
}

