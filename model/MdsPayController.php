<?php
/**
*米大师虚拟币支付接口
*@author hedongji
*@since 2019-04-09 build
*/
 namespace weapi\model;

defined('IN_API') or exit('Access Denied');

use weapi\config\MdspayConfig;
use weapi\util\Tools;
use weapi\util\Log;
use weapi\util\DB;
class ErrorCode
{
	private static $msd_0='请求成功';
	private static $msd_90009='mp_sig签名错误';
	private static $msd_90010='用户未登录或登录态已过期';
	private static $msd_90011='sig签名错误';
	private static $msd_90012='订单已存在';
	private static $msd_90013='余额不足';
	private static $msd_90014='订单已支付确认完成，不允许当前操作';
	private static $msd_90015='订单已回退，不允许当前操作';
	private static $msd_90016='订单处理中';
	private static $msd_90017='没有调用接口的权限';
	private static $msd_90018='参数错误';
	
	public static function getErrorMsg($code){
		if ($code == -1) {
			return '系统繁忙，此时请开发者稍候再试';
		}else{
			$error_code = "msd_".$code;
			if(isset(self::$$error_code)){
				return self::$$error_code;
			}else{
				return "未知错误,code=".$code;
			}
		}
	}
}
class MdsPay{   
	const HOST = 'https://api.weixin.qq.com';
	/****************************沙箱环境******************************************/
	private $SD_API_QUERY = '/cgi-bin/midas/sandbox/getbalance';//沙箱查询余额接口
	private $SD_API_PAY = "/cgi-bin/midas/sandbox/pay";//沙箱扣除游戏币
	private $SD_API_CANCEL_PAY = "/cgi-bin/midas/sandbox/cancelpay";//沙箱取消订单
	private $SD_API_PRESENT = "/cgi-bin/midas/sandbox/present";//沙箱给用户赠送游戏币
	/*****************************************************************************/
	private $API_QUERY = '/cgi-bin/midas/getbalance';//现网环境查询余额接口
	private $API_PAY = "/cgi-bin/midas/pay";//扣除游戏币
	private $API_CANCEL_PAY = "/cgi-bin/midas/cancelpay";//取消订单
	private $API_PRESENT = "/cgi-bin/midas/present";//给用户赠送游戏币

	private $cookie = array();
	private $log;


	function __construct($auth){
		$this->log = new Log("MDSPAY",30);
	}
	/**
	* 查询余额接口
	* @param $openid 微信openid
	*/
	public function getBalance($openid,$app,$sandbox=false){
		$config = MdspayConfig::$$app;
		$this->setPayCookie($config,"QUERY",$sandbox);

		$body = array(
			"openid"=>$openid,
			"appid"=>$config['appid'],
			"offer_id"=>$config['offer_id'],
			"ts"=>time(),
			"pf"=>"android",
			"zone_id"=>"1",
			"sig"=>"",
			"access_token"=>"",
			"mp_sig" => ""
		);
		
		$rel = $this->requestMdsApi($openid,$body,$app);
		//stdClass Object ( [errcode] => 0 [errmsg] => ok [balance] => 2 [gen_balance] => 0 [first_save] => 0 [save_amt] => 2 [save_sum] => 2 [cost_sum] => 0 [present_sum] => 0 )
		if (!is_object($rel)) {
			return $rel;
		}
		if ($rel->errcode == 0) {
			return array("balance" => $rel->balance);
		}else{
			return ErrorCode::getErrorMsg($rel->errcode);
		}
	}
	private function setPayCookie($config,$type,$sandbox){
		if ($sandbox===true) {
			$appkey = $config['sd_appkey'];
			$type = "SD_API_".$type;
		}else{
			$appkey = $config['appkey'];
			$type = "API_".$type;
		}
		$org_loc = $this->$type;
		$this->cookie = array(
			"session_id"=>"hy_gameid",
			"session_type"=>"wc_actoken",
			"org_loc"=>$org_loc,
			"appip"=>$config['appid'],
			"appkey"=>$appkey,
		);
	}
	/**
	* 扣除游戏币
	* @param 
	**/
	public function pay($orderinfo,$openid,$app,$sandbox=false){
		$bill_no = $orderinfo['transactId'];
		if (empty($bill_no)) {
			return false;
		}

		$config = MdspayConfig::$$app;
		$this->cookie = array();
		$this->setPayCookie($config,"PAY",$sandbox);
		$body = array(
			"openid"=>$openid,
			"appid"=>$config['appid'],
			"offer_id"=>$config['offer_id'],
			"ts"=>time(),
			"zone_id"=>"1",
			"pf"=>"android",
			"amt" => "$amt",
			"bill_no" => "$bill_no",//内部订单号
			"sig"=>"",
			"access_token"=>"",
			"mp_sig" => ""
		);
		$rel = $this->requestMdsApi($openid,$body,$app);
		$requestTime = 0;
		while ($rel===false&&@$requestTime<5) {
			$requestTime++;
			$rel = $this->requestMdsApi($openid,$body,$app);
		}
		if (!is_object($rel)) {
			return $rel;
		}
		//stdClass Object ( [errcode] => 0 [errmsg] => ok [bill_no] => 20181227093354-4875 [balance] => 1 [used_gen_amt] => 0 )
		if ($rel->errcode == 0) {
			return true;
		}else{
			return ErrorCode::getErrorMsg($rel->errcode);
		}
	}
	/**
	* 取消订单
	* @param 
	* 
	**/
	public function cancelpay($openid,$bill_no,$app,$sandbox=false){
		$config = MdspayConfig::$$app;
		$this->cookie = array();
		$this->setPayCookie($config,"CANCEL_PAY",$sandbox);
		$body = array(
			"openid"=>$openid,
			"appid"=>$config['appid'],
			"offer_id"=>$config['offer_id'],
			"ts"=>time(),
			"zone_id"=>"1",
			"pf"=>"android",
			"bill_no" => "$bill_no",//内部订单号
			"sig"=>"",
			"access_token"=>"",
			"mp_sig" => ""
		);
		$rel = $this->requestMdsApi($openid,$body,$app);
		//stdClass Object ( [errcode] => 0 [errmsg] => ok [bill_no] => 20181227092105-3754 )
		if (!is_object($rel)) {
			return $rel;
		}
		if ($rel->errcode == 0) {
			return $this->getBalance($openid,$app,$sandbox);
		}else{
			return ErrorCode::getErrorMsg($rel->errcode);
		}
	}
	private function requestMdsApi($openid,$body,$app){

		if(!($body['access_token'] = $this->getAccessToken($app))) return "request access_token fault";
		if(!($session_key = $this->getSessionKey($openid,$app))) return "request session_key fault";
		$sig = self::sig($body,$this->cookie['org_loc']);
		$body['sig'] = $sig;
		$mp_sig = self::mp_sig($body,$this->cookie['org_loc'],$session_key);
		$body['mp_sig'] = $mp_sig;
		$url = self::HOST.$this->cookie['org_loc']."?access_token=".$body['access_token'];
		unset($body['access_token']);
		$rel = $this->curlPost($url,$body);
		$rel = json_decode($rel);
		if ($rel->errcode || $rel->errmsg != "ok") {
			if ($rel->errcode == -1) {
				return "米大师接口请求错误";
			}
			return ErrorCode::getErrorMsg($rel->errcode);
		}else{
			return $rel;
		}
	}
	private function buildOrder($productid="",$uid=0){
	    if (empty($productid)) {
	     	echo json_encode(array("code"=>-1,"msg"=>'订单创建失败,缺少参数'),JSON_UNESCAPED_UNICODE);die;
	     }
	     if (empty($uid)) {
	     	echo json_encode(array("code"=>-1,"msg"=>'订单创建失败,缺少参数'),JSON_UNESCAPED_UNICODE);die;
	     }
	    $dataArr = array('productcount' => 1,'productid' =>$productid,'uid' =>$uid,'authentic' => '');
		$orderArr = $this->getJsonArray("http://192.168.1.94/admin/recharge/recproductreq",$dataArr);
		if($orderArr==false){
			echo json_encode(array("code"=>-1,"msg"=>'订单创建失败'),JSON_UNESCAPED_UNICODE);die;
		}else{
			if($orderArr['return']==1){
				return $orderArr;
			}else{
				echo json_encode(array("code"=>-1,"msg"=>'订单创建失败'),JSON_UNESCAPED_UNICODE);die;
			}
		}	  
	}
	private function getAccessToken($app){
		$token_rel = $this->curlPost('https://pubapi.nbgame.cn/weapi/?m=WechatApi&a=getAccessToken&app='.$app,[]);
		$token_rel = json_decode($token_rel,true);
		return isset($token_rel['msg'])?$token_rel['msg']:"";
	}
	private function getSessionKey($openid,$app){
		$this->db = new DB(); 
		switch ($app) {
			case 'NBDZ_APPLIT':
				$sql = "select * from  newecho.h5dz_applet_sessionkey where openid='".$openid."'";
				break;
			case 'NBMJ_APPLIT':
				$sql = "select * from  newecho.h5mj_applet_sessionkey where openid='".$openid."'";
				break;
			default:
				return ;
				break;
		}
        
        $rel = $this->db->row($sql);
		return isset($rel['sessionkey'])?$rel['sessionkey']:"";
	}
	public function sig($body,$url_path){
		$secret = $this->cookie['appkey'];
		$sig = self::makeSig('POST', $url_path, $body, $secret) ;
		return $sig;
	}
	public function mp_sig($body,$url_path,$session_key=''){
		$mp_sig = self::makeMpSig('POST', $url_path, $body, $session_key) ;
		return $mp_sig;
	}
	static public function makeSig($method, $url_path, $params, $secret){
		$strs = '&org_loc=' . ($url_path). '&method='.strtoupper($method)  . '&secret=' . $secret;
		unset($params['sig']);unset($params['access_token']);unset($params['mp_sig']);
		ksort($params);
		$query_string = array();
        foreach ($params as $key => $val ) 
        { 
            array_push($query_string, $key . '=' . $val);
        }   
        $query_string = join('&', $query_string);
        $mk = str_replace('~', '%7E', ($query_string)) .$strs;
        $my_sign = hash_hmac('sha256', $mk, $secret);
        return $my_sign;
	}
	static public function makeMpSig($method, $url_path, $params, $session_key){
		// echo $url_path;die;
		$strs = '&org_loc=' . $url_path. '&method='.strtoupper($method)  . '&session_key=' . $session_key;
		unset($params['mp_sig']);
		ksort($params);
		$query_string = array();
        foreach ($params as $key => $val ) 
        { 
            array_push($query_string, $key . '=' . $val);
        }  
        $query_string = join('&', $query_string);
        $mk = str_replace('~', '%7E', ($query_string)) .$strs;
        $my_sign = hash_hmac('sha256', $mk, $session_key);
        return $my_sign;
	}
	private function curlPost($url,$body,$cookie="",$header=""){
		$body = json_encode($body);
        $curl = curl_init ();
        curl_setopt ( $curl, CURLOPT_URL, $url );
        curl_setopt ( $curl, CURLOPT_POST, 1 );
        curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($curl, CURLOPT_TIMEOUT, 10); 
        if ($header) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);  
        }
        
        if ($cookie) {
        	curl_setopt($curl, CURLOPT_COOKIE,$cookie);
        }
        if (stripos($url, "https://") !== false) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSLVERSION, 1);
        }  
        curl_setopt ( $curl, CURLOPT_POSTFIELDS, $body );
        $return = curl_exec ( $curl );
        curl_close ( $curl );
        return $return;
	}
	private function getJsonArray($url,$arr){
		if($url=="")
		{
			return false;
		}
		$o=json_encode($arr,JSON_UNESCAPED_UNICODE);
		$o=iconv("UTF-8","GBK",$o);
		$this_header = array("content-type: application/x-www-form-urlencoded;charset=GBK");
		$ch = curl_init();
		curl_setopt($ch,CURLOPT_HTTPHEADER,$this_header);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $o);
		$result=curl_exec($ch);
		$backStr=@iconv("gbk","UTF-8",$result);
		$backArr=json_decode($backStr,true);
		return $backArr;
	}
	private function getCookieStr($cookie){
		$cookie_str = "";
		foreach ($cookie as $key => $value) {
			$cookie_str .= $key."=". $value.";";
		}
		return $cookie_str;
	}
}
