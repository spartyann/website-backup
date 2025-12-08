<?php

require_once __DIR__ . '/config.php';


$file = __DIR__ . '/dump.sql';

if (file_exists($file)) {
	unlink($file);
}

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



if (downloadFileWithCurl(URL, $file) == false){
	echo "Error downloading file from URL \n";
	exit(1);
}

// Utilisation
if (importViaMysqlCommand(
    $file,
    DB_HOST,
    DB_USER,
    DB_PWD,
    DB_DB
) == false) {
	echo "Error importing file from URL \n";
	exit(1);
}

