<?php

function classLoader($m)
{
	$m = str_replace('\\', DIRECTORY_SEPARATOR, $m);
	if ($m == 'weapi/model/Exception') {
		return;
	}
	$ModelFile = "";
	if (strpos($m, 'weapi'.DIRECTORY_SEPARATOR) !==false) {
	    $ModelPath = str_replace('weapi'.DIRECTORY_SEPARATOR, '', $m);
		if ($ModelPath === 'App'||$ModelPath === 'ErrorCode') {
			$ModelFile = __DIR__ .DIRECTORY_SEPARATOR .$ModelPath . '.php';
		}elseif(strpos($ModelPath, "util".DIRECTORY_SEPARATOR) !==false ||strpos($ModelPath, "config".DIRECTORY_SEPARATOR) !== false ||strpos($ModelPath, "wemq".DIRECTORY_SEPARATOR) !== false){
			$ModelFile = __DIR__ .DIRECTORY_SEPARATOR .$ModelPath . '.php';
		}else{
			$ModelFile = __DIR__ .DIRECTORY_SEPARATOR .$ModelPath . 'Controller.php';
		}
	}else{
		$ModelFile = __DIR__ .DIRECTORY_SEPARATOR .'vendor'.DIRECTORY_SEPARATOR.$m . '.php';
	}
	
    if (file_exists($ModelFile)) {
        require_once $ModelFile;
    }else{
    	echo $ModelFile;die;
    	header("Location:"."./404.html");exit();
    }
}
spl_autoload_register('classLoader');