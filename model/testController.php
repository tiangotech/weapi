<?php
/**
*@author hedongji
*@since 2019-01-09 build
*/
namespace weapi\model;

defined('IN_API') or exit('Access Denied');
use weapi\util\Tools;
use weapi\util\Job;
use weapi\util\Log;
use weapi\model\WechatPay;
use weapi\model\Order;
class test {

	protected $log;

	public function __construct(){
		$this->log = new Log("emailtest",1,["1406835034@qq.com"]);
	}

	public function test(){
		$url = $this->getHttpHost();
		
		die($url);
		$this->log->error(1);
		var_dump($rel );
	}


	public function pay($uid){

		$orderinfo = array('price'=>1,'detail'=> '商品描述','tradeno'=>time().rand(1000,9999));
		$app = 'TG_PUB';
		$tradetype = "JSAPI";
		$platform = "六度空间";
		$info = WechatPay::pay($orderinfo,$app,$tradetype,$platform,'','ouDJK6D0Tu9KLwwJLNIE6WpGzS9U');
		return $info;
	}

	private function getHttpHost(){
		$http = "http://";
		if ($_SERVER['SERVER_PORT'] == 443) {
			$http = "https://";
		}
		$request_uri = explode("?", $_SERVER['REQUEST_URI']);

		$notify =  $http.$_SERVER['HTTP_HOST'].$request_uri[0]."paynotify.php";
		$array = get_headers($notify,1);
		if(preg_match('/200/',$array[0])){
			return $notify;
		}else{
			return false;
		}
	}
}
