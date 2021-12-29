<?php

namespace App\Tools;


class FileTools 
{
    public static function getReadableSize($bytes, $decimals = 2){
		$size = array('B','kB','MB','GB','TB','PB','EB','ZB','YB');
		$factor = floor((strlen($bytes) - 1) / 3);
		return sprintf("%.{$decimals}f", $bytes / pow(1024, $factor)) . ' ' . @$size[$factor];
	}

}
