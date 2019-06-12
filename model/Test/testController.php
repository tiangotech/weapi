<?php
/**
*@author hedongji
*@since 2019-01-09 build
*/
namespace weapi\model\Test;

defined('IN_API') or exit('Access Denied');
use weapi\util\Tools;
use weapi\util\Job;
use weapi\util\Log;
class test {

	protected $log;

	public function __construct(){
		$this->log = new Log("emailtest",1,["1406835034@qq.com"]);
	}

	public function emailtest(int $uid){
		echo $uid;die;
	}

}