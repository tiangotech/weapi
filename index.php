<?php 
namespace weapi;
header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Methods:*');
header('Access-Control-Allow-Headers:x-requested-with,content-type');
ini_set('date.timezone','Asia/Shanghai');
ini_set("display_errors", "On");
define('IN_API',true);
define('V_CONFIG', __DIR__."/config/VersionConfig.php");
require_once __DIR__ . '/autoload.php';
App::exception_error();
App::run();
