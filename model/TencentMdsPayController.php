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
class TencentMdsPay{

	
	/****************************沙箱环境******************************************/
	const SBHOST = 'https://ysdktest.qq.com';
	/*****************************************************************************/
	const HOST = 'https://ysdk.qq.com';
	private $API_QUERY = '/mpay/get_balance_m';//现网环境查询余额接口
	private $API_PAY = "/mpay/pay_m";//扣除游戏币
	private $API_CANCEL_PAY = "/mpay/cancel_pay_m";//取消订单
	private $API_PRESENT = "/mpay/present_m";//给用户赠送游戏币
	private $API_CHECK_TOKEN_QQ = "/auth/qq_check_token";//沙箱用户登录验证
	private $API_CHECK_TOKEN_WX = "/auth/wx_check_token";//沙箱用户登录验证

	private $cookie = array();
	private $log;


	function __construct(){
		$this->log = new Log("TencentMdsPay",30);
	}
	/**
	* 
	* @param $openid：从手Q登录态或微信登录态中获取的openid的值
	* @param $openkey：手Q登陆时传手Q登陆回调里获取的paytoken值，微信登陆时传微信登陆回调里获取的传access_token值。
	* @param $openkey 微信登录态填的是登录时获取到的accessToken
	* @param $pf：平台来源，登录获取的pf值
	* @param $pfkey：登录获取的pfkey值
	* @param $zoneid 游戏服务器大区id,游戏不分大区则默认zoneId ="1",String类型。
	*/
	public function checkToken($unionid,$openid,$nickname,$headimgurl,$openkey,$platform="8192",$loginType="wx",$app,$sandbox=false,$version="0.0.0",$extra="",$mac1="",$mac2=""){
		if (!in_array($loginType, ['wx','qq'])) return "请确定登录类型:微信（wx），手q（qq）";
		$this->host = $sandbox?self::HOST:self::SBHOST;
		$this->api = ($loginType=="wx")?$this->API_CHECK_TOKEN_WX:$this->API_CHECK_TOKEN_QQ;
		$config = MdspayConfig::$$app;
		$appid = $config['app'][$loginType]['appid'];
		$appkey = $config['app'][$loginType]['appkey'];
		$this->setPayCookie($config,$loginType,$sandbox);
		$timestamp = time();
		$body = array(
			"openid"=>$openid,
			"openkey"=>"$openkey",
			"appid"=>$appid,
			"sig"=> md5($appkey.$timestamp ),
			"timestamp"=>$timestamp,
		);
		$url = $this->host.$this->api."?timestamp=".$body['timestamp']."&appid=".$body['appid']."&sig=".$body['sig']."&openid=".$body['openid']."&openkey=".rawurlencode($body['openkey']);
		$rel = $this->curlPost($url,[]);
		$result = json_decode($rel,true);
		if ($result['ret'] == 0) {
			$userinfo['openid'] = $openid;
			$userinfo['nickname'] = $nickname;
			$userinfo['unionid'] = $unionid;
			$userinfo['headimgurl'] = $headimgurl;
			$rel = WechatLogin::wechatLoginCommon($userinfo,$platform,$extra,1,"",$version,$mac1,$mac2);
			return $rel;
		}else{
			return $result['msg'];
		}
		
	}
	
	/**
	* 查询余额接口
	* @param $openid：从手Q登录态或微信登录态中获取的openid的值
	*/
	public function getBalance($openid,$openkey,$pf,$pfkey,$loginType="wx",$app,$sandbox=false){
		if (!in_array($loginType, ['wc','kp'])) return "请确定登录类型:微信（wc），手q（kp）";
		$this->host = $sandbox?self::HOST:self::SBHOST;
		$this->api = ($loginType=="wx")?$this->API_CHECK_TOKEN_WX:$this->API_CHECK_TOKEN_QQ;
		$config = MdspayConfig::$$app;
		$this->setPayCookie($config,$loginType,$sandbox);
		$body = array(
			"openid"=>$openid,
			"openkey"=>$openkey,
			"appid"=>$config['offer_id'],
			"ts"=>time(),
			"sig"=>"",
			"pf"=>$pf,
			"pfkey"=>$pfkey,
			"zoneid"=>"1",
		);
		
		$rel = $this->requestMdsApi($openid,$body,$app);
		return $rel;
	}
	private function setPayCookie($config,$loginType,$sandbox){
		$appkey = $sandbox?$config['sd_appkey']:$config['appkey'];
		$org_loc = $this->api;
		$this->cookie = array(
			"session_id"=>$config['session_id'][$loginType],
			"session_type"=>$config['session_type'][$loginType],
			"org_loc"=>$org_loc,
			"appid"=>$config['appid'],
			"appkey"=>$appkey,
			"offer_id" =>$config['offer_id'],
			"app"=>$config['app']
		);
	}
	/**
	* 扣除游戏币
	* @param 
	**/
	public function pay($orderinfo,$openid,$openkey,$pf,$pfkey,$loginType="wx",$app,$sandbox=false){
		if (!in_array($loginType, ['wx','qq'])) return "请确定登录类型:微信（wx），手q（qq）";
		$this->host = $sandbox?self::HOST:self::SBHOST;
		$this->api = ($loginType=="wx")?$this->API_CHECK_TOKEN_WX:$this->API_CHECK_TOKEN_QQ;
		$config = MdspayConfig::$$app;
		$this->setPayCookie($config,$loginType,$sandbox);
		$balance = $this->getBalance($openid,$openkey,$pf,$pfkey,$loginType,$app,$sandbox);
		if (!is_array($balance)) {
			return $balance;
		}
		if ($balance['balance'] < $amt) {
			return "余额不足";
		}

				if ($orderinfo['productPrice'] == $amt) {
					return "购买支付的金额和产品的金额不一样";
				}
				$config = MdspayConfig::$$app;
				$this->cookie = array();
				$this->setPayCookie($config,$loginType,"QUERY",$sandbox);
				$body = array(
					"openid"=>$openid,
					"openkey"=>$openkey,
					"appid"=>$config['offer_id'],
					"ts"=>time(),
					"sig"=>"",
					"pf"=>"android",
					"pfkey"=>$pfkey,
					"zoneid"=>"1",
					"amt" => "$amt",
					"billno" => "$bill_no"//百迅内部订单号
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
					$dataArr = array('inaccount' => $app,'payaccount' => $openid,'paywayid' => 10 ,'ordercodeout' => $bill_no,'ordercodein' =>$bill_no,'authentic' => '','uid' => 0);
					//=====================完成订单业务处理========================

					//=============================================================
					if ($payArr['return'] == 1) {
						return array("balance" => $rel->balance);
					}else{
						if($this->cancelpay($openid,$bill_no,$app,$sandbox)){
							return "网络故障购买失败，请稍后再次尝试";
						}else{
							$this->log->critical("购买失败".json_encode($payArr));
							return "网络故障购买失败，联系客服，待确认后将予补偿";
						}
					}
				}else{
					$this->log->critical("扣除游戏币失败，请稍后再尝试".json_encode($rel));
					return "扣除游戏币失败";
				}
	
	}
	/**
	* 取消订单
	* @param 
	* 
	**/
	public function cancelpay($openid,$openkey,$pf,$pfkey,$loginType="wc",$bill_no,$app,$sandbox=false){
		$config = MdspayConfig::$$app;
		$this->cookie = array();
		$this->setPayCookie($config,$loginType,"CANCEL_PAY",$sandbox);
		$body = array(
			"openid"=>$openid,
			"openkey"=>$openkey,
			"appid"=>$config['offer_id'],
			"ts"=>time(),
			"sig"=>"",
			"pf"=>"android",
			"pfkey"=>$pfkey,
			"zoneid"=>"1",
			"amt" => "$amt",
			"billno" => "$bill_no"//百迅内部订单号
		);
		$rel = $this->requestMdsApi($openid,$body,$app);
		return $this->getBalance($openid,$app,$sandbox);
	}
	private function requestMdsApi($openid,$body,$app){
		$sig = self::sig($body,$this->cookie['org_loc']);
		$body['sig'] = urlencode($sig);
		$url = $this->host.$this->cookie['org_loc'];
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

	public function sig($body,$url_path){
		$secret = $this->cookie['appkey'];
		$sig = self::makeSig('POST', $url_path, $body, $secret) ;
		return $sig;
	}
	static public function makeSig($method, $url_path, $params, $secret){
		$strs = $method.'&' . urlencode('/v3/r'.$url_path);
		unset($params['sig']);
		ksort($params);
		$query_string = array();
        foreach ($params as $key => $val ) 
        { 
            array_push($query_string, $key . '=' . $val);
        }   
        $query_string = join('&', $query_string);

        $param_str = urlencode($query_string);

        $mk = $strs.'&'.str_replace('~', '%7E', $param_str);
        $my_sign = hash_hmac('sha256', $mk, $secret.'&');
        return base64_encode($my_sign);
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
	/**
	 * 执行一个 HTTP 请求
	 *
	 * @param string 	$url 	执行请求的URL 
	 * @param mixed	$params 表单参数
	 * 							可以是array, 也可以是经过url编码之后的string
	 * @param mixed	$cookie cookie参数
	 * 							可以是array, 也可以是经过拼接的string
	 * @param string	$method 请求方法 post / get
	 * @param string	$protocol http协议类型 http / https
	 * @return array 结果数组
	 */
	static public function makeRequest($url, $params, $cookie, $method='post', $protocol='http')
	{   
		// $query_string = self::makeQueryString($params);	   
	    // $cookie_string = self::makeCookieString($cookie);
	    	    
	    $ch = curl_init();

	    if ('get' == $method)
	    {
		    curl_setopt($ch, CURLOPT_URL, "$url");
	    }
	    else 
        {
		    curl_setopt($ch, CURLOPT_URL, $url);
		    curl_setopt($ch, CURLOPT_POST, 1);
		    curl_setopt($ch, CURLOPT_POSTFIELDS, $query_string);
	    }
        
	    curl_setopt($ch, CURLOPT_HEADER, false);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);

        // disable 100-continue
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));

	    if (!empty($cookie_string))
	    {
	    	curl_setopt($ch, CURLOPT_COOKIE, $cookie_string);
	    }
	    
	    if ('https' == $protocol)
	    {
	    	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	    }
	
	    $ret = curl_exec($ch);
	    $err = curl_error($ch);
	    
	    if (false === $ret || !empty($err))
	    {
		    $errno = curl_errno($ch);
		    $info = curl_getinfo($ch);
		    curl_close($ch);

	        return array(
	        	'result' => false,
	        	'errno' => $errno,
	            'msg' => $err,
	        	'info' => $info,
	        );
	    }
	    
       	curl_close($ch);

        return array(
        	'result' => true,
            'msg' => $ret,
        );
	            
	}
}
