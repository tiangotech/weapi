<?php
/**
*微信登录相关类
*@author hedongji
*@since 2019-01-07 build
*/
namespace weapi\model;

defined('IN_API') or exit('Access Denied');
use weapi\config\WechatApiConfig;
use weapi\config\Config;
use weapi\config\WechatPayConfig;
use weapi\model\Order;
use weapi\util\Tools;
use weapi\util\Log;
class WechatPay {

	public static $log;

	private $redirect_url;//MWEB 支付完成时跳转页面
	private $referer;//MWEB 调起支付的页面域名
	/**
     * 支付
     * @access public
     * @param array   $orderinfo = array('price'=>"商品价格",'detail'=> '商品描述','tradeno'=>'订单号') 订单信息
     * @param string  $app    		 应用 需在config/WechatApiConfig配置
     * @param string  $tradetype     支付方式 ['JSAPI','NATIVE','APP','MWEB']JSAPI--JSAPI支付（或小程序支付）、NATIVE--Native支付、APP--app支付，MWEB--H5支付，不同trade_type决定了调起支付的方式，请根据支付产品正确上传  MICROPAY--付款码支付，付款码支付有单独的支付接口，所以接口不需要上传，该字段在对账单中会出现
     * @param string  $mch         	 商户平台['LDKJ']
     * @param int     $platform      支付平台
     * @param string  $openid        openid （JSAPI支付需要）
     * @param bool    $saveandprint    NATIVE支付生成的二维码是否保存后返回数组参数，否：直接访问二维码
     * @param string  $redirect_url        MWEB 支付完成时跳转页面
     * @param string  $referer        MWEB 调起支付的页面域名
     */
	public static function pay($orderinfo,$app,$tradetype,$platform,$mch="",$openid='',$saveandprint=false,$redirect_url="",$referer="") {

		self::$log = new Log("WECHATPAY",30);

		if ($redirect_url) $this->redirect_url = $redirect_url;
		if ($referer) $this->referer = $referer;

		$price = intval($orderinfo['price'])*100;
		$detail = $orderinfo['detail'];
		$codein = $orderinfo['tradeno'];
		if (!in_array($tradetype, ['JSAPI','NATIVE','APP','MWEB'])) return 50015;

		$appconfig = WechatApiConfig::getAppConfig($app);
		if ($appconfig === false) {
			return 40011;
		}
		$appID = $appconfig["APPID"];
		$secret = $appconfig["APPSECRET"];

		if (empty($mch)) {
			$mch = WechatPayConfig::payAppUnionMch($app,$tradetype);
		}
		if (!in_array($mch, WechatPayConfig::MTC)) return 50014;

		$mchInfo = WechatPayConfig::$$mch;
		if (empty($mchInfo)) return 50014;

		$notify_url = $this->getHttpHost();
		if ($notify_url === false) return "notify_url is error";
		//============统一支付参数数据=========================
		$data['appid'] = $appID;
		$data['mch_id'] = $mchInfo['mchid'];
		$data['device_info'] = $mch;//自定义参数，可以为终端设备号(门店号或收银设备ID)，PC网页或公众号内支付可以传"WEB"
		$data['nonce_str'] = Tools::createNonceStr();
		// $data['sign'] = "";
		// $data['sign_type'] = '';
		$data['body'] = $detail;
		$data['out_trade_no'] = $codein;
		// $data['fee_type'] = ;
		$data['total_fee'] = $price;
		$data['spbill_create_ip'] = Tools::get_client_ip();//$_SERVER['REMOTE_ADDR'];
		$data['time_start'] = date("YmdHis");
		$data['time_expire'] = date("YmdHis", time() + 600);
		// $data['goods_tag'] = ;
		$data['notify_url'] = $notify_url;
		$data['trade_type'] = $tradetype;
		if ($tradetype == 'NATIVE') $data['product_id'] = $codein;//trade_type=NATIVE时，此参数必传。此参数为二维码中包含的商品ID，商户自行定义。
		// $data['limit_pay'] = ;
		// self::$log->critical(json_encode($data));
		if ($tradetype == 'JSAPI') {
			if (empty($openid)) return "缺少openid";
			$data['openid'] = $openid;//trade_type=JSAPI时（即JSAPI支付），此参数必传，此参数为微信用户在商户对应appid下的唯一标识
		}
		// $data['receipt'] = ;
		// $data['scene_info'] = ;
		//======================================================
		$rel = self::unifiedOrder($data,$mchInfo);
		if (isset($rel['return_code']) && $rel['return_code']=="SUCCESS") {
			//用户调用微信支付统一接口日志
			self::$log->notice("平台[".$platform."]发起[".$tradetype."]微信支付(商户".$mch.")，内部订单号[".$codein."]");
			unset($rel['return_code']);unset($rel['return_msg']);unset($rel['result_code']);
			switch ($tradetype) {
				case 'JSAPI':
					$jsApiParameters = self::GetJsApiParameters($rel,$mchInfo);
					if (!is_array($jsApiParameters)) {
						self::$log->critical("获取微信支付参数失败，原因：".json_encode($rel,JSON_UNESCAPED_UNICODE));
						$result = $rel['err_code_des'];
					}else{
						$result['jsApiParameters'] = $jsApiParameters;
					}
					break;
				case 'APP':
					$params =array(
						'prepayid' => $rel['prepay_id'],
						'appid' => $rel['appid'],
						'partnerid' => $rel['mch_id'],
						'package' => 'Sign=WXPay',
						'noncestr' => $rel['nonce_str'],
						'timestamp' => (string)time()
					);
					$result['info'] = json_encode($params);
					$sign = self::MakeSign($params,$mchInfo);
  		      			$result['sign'] = $sign;
					break;
				case 'NATIVE':
					include_once __DIR__.'/../vendor/phpQrcode/phpqrcode.php';
					$url = urldecode($rel['code_url']);
					if(substr($url, 0, 6) == "weixin"){
						if ($saveandprint) {
							$dir = __DIR__.'/../log/QRimg/';
							if (!file_exists($dir)){
								mkdir($dir,0777,true);
							}
							\QRcode::png($url, $dir.$codein.".png",QR_ECLEVEL_L,3,4);
							$qr = self::base64EncodeImage($dir.$codein.".png");
							if (file_exists(__DIR__.'/log/QRimg/'.$codein.".png")) {
								if(!unlink(__DIR__.'/log/QRimg/'.$codein.".png")){
									self::$log->critical("订单[".$codein."],删除二维码文件失败");
								}
							}
							return array("qr"=>$qr,"trade_no"=>$codein);
						}else{
							\QRcode::png($url);die;
						}
					}
					break;
				case 'MWEB':
  		    				$wx_url =  $rel['mweb_url']."&redirect_url=".$this->redirect_url;
					if ($rel['mweb_url']) {
					    $ch = curl_init();
						curl_setopt ($ch, CURLOPT_URL, $wx_url);
						curl_setopt ($ch, CURLOPT_REFERER, $this->referer);
						curl_exec ($ch);
						curl_close ($ch);die;
					}else{
					    return 50996;
					}
					break;
			}
			return $result;
		}
		self::$log->critical("获取微信支付参数失败，原因：".json_encode($rel,JSON_UNESCAPED_UNICODE));
		if (isset($rel['return_code']) && $rel['return_code']=="FAIL") {
			return $rel['return_msg'];
		}
		return 50996;
	}
	public static function base64EncodeImage ($image_file) {
	    $base64_image = '';
	    $image_info = getimagesize($image_file);
	    $image_data = fread(fopen($image_file, 'r'), filesize($image_file));
	    $base64_image = 'data:' . $image_info['mime'] . ';base64,' . chunk_split(base64_encode($image_data));
	    return $base64_image;
	}
	/**
     * 微信统一下单
     * @access public
     * @param array  $data     订单数据
     * @param array   $mchInfo         商户参数
     */
	public static function unifiedOrder($data,$mchInfo){
		$data['sign'] = self::MakeSign($data,$mchInfo);
		$xml = self::ToXml($data);
		if($xml == false) return 50016;
		$url = "https://api.mch.weixin.qq.com/pay/unifiedorder";
		$response = self::postXmlCurl($xml, $url, true,6,$mchInfo);
		if ($response['status']>0) {
			return $response['msg'];
		}
		$startTimeStamp = self::getMillisecond();//请求开始时间
		$rel = self::resolveRel($response['data'],$mchInfo);
		//上报数据 可走队列
		self::reportCostTime($data['appid'],$mchInfo,$url, $startTimeStamp, $rel);
		return $rel;
	}
	/**
     * 检查扫码支付是否成功
     * @access public
     * @param array  $trade_no     订单
     */
	public static function checkNativePayIsok($trade_no){
		if (file_exists(__DIR__.'/../log/QRimg/'.$trade_no.".png") === false) {
			return true;
		}else{
			return 50024;
		}
	}
	/**
	 * 以post方式提交xml到对应的接口url
	 * 
	 * @param string $xml  需要post的xml数据
	 * @param string $url  url
	 * @param bool $useCert 是否需要证书，默认不需要
	 * @param int $second   url执行超时时间，默认30s
	 * @throws WxPayException
	 */
	public static function postXmlCurl($xml, $url, $useCert = false, $second = 30,$mchInfo){		
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);
		if(WechatPayConfig::CURL_PROXY_HOST != "0.0.0.0" 
			&& WechatPayConfig::CURL_PROXY_PORT != 0){
			curl_setopt($ch,CURLOPT_PROXY, WechatPayConfig::CURL_PROXY_HOST);
			curl_setopt($ch,CURLOPT_PROXYPORT, WechatPayConfig::CURL_PROXY_PORT);
		}
		curl_setopt($ch,CURLOPT_URL, $url);
		if(stripos($url,"https://")!==FALSE){
	        curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1);//
	        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
	    }else{
	        curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,TRUE);
	        curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);
		} 
		curl_setopt($ch, CURLOPT_HEADER, FALSE);
		//要求结果为字符串且输出到屏幕上
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		if($useCert == true){
			curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLCERT, $mchInfo['sslcert_path']);
			curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLKEY, $mchInfo['sslckey_path']);
		}
		curl_setopt($ch, CURLOPT_POST, TRUE);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		$data = curl_exec($ch);
		$rel['status'] = 0;
		$rel['data'] = $data;
		if($data){
			curl_close($ch);
			return $rel;
		} else { 
			$error = curl_errno($ch);
			curl_close($ch);
			$rel['status'] = 50017;
			$rel['msg'] = "请求微信接口出错，错误码:$error";
			return $rel;
		}
	}
	/**
	 * 
	 * 获取jsapi支付的参数
	 * @param array $UnifiedOrderResult 统一支付接口返回的数据
	 * @throws WxPayException
	 * 
	 * @return json数据，可直接填入js函数作为参数
	 */
	private static function GetJsApiParameters($UnifiedOrderResult,$mchInfo){
		if(!array_key_exists("appid", $UnifiedOrderResult)
		|| !array_key_exists("prepay_id", $UnifiedOrderResult)
		|| $UnifiedOrderResult['prepay_id'] == ""){
			return 5020;
		}
		$data['appId'] = $UnifiedOrderResult["appid"];
		$timeStamp = time();
		$data['timeStamp'] = (string)$timeStamp;
		$data['nonceStr'] = Tools::createNonceStr();
		$data['package'] = "prepay_id=" . $UnifiedOrderResult['prepay_id'];
		$data['signType'] = "MD5";
		$data['paySign'] = self::MakeSign($data,$mchInfo);
		return $data;
	}
	/**
	 * 
	 * 获取地址js参数
	 * 
	 * @return 获取共享收货地址js函数需要的参数，json格式可以直接做参数使用
	 */
	private static function GetEditAddressParameters($UnifiedOrderResult,$mchInfo){	
		$getData = null;
		$data = array();
		$data["appid"] = $UnifiedOrderResult['appid'];
		$data["url"] = "http://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'];
		$time = time();
		$data["timestamp"] = "$time";
		$data["noncestr"] = Tools::createNonceStr();
		$data["accesstoken"] = $getData["access_token"];
		ksort($data);
		$params = self::ToUrlParams($data);
		$addrSign = sha1($params);
		
		$afterData = array(
			"addrSign" => $addrSign,
			"signType" => "sha1",
			"scope" => "jsapi_address",
			"appId" => $UnifiedOrderResult['appid'],
			"timeStamp" => $data["timestamp"],
			"nonceStr" => $data["noncestr"]
		);
		$parameters = json_encode($afterData);
		return $parameters;
	}
	/**
	 * 
	 * 上报数据， 上报的时候将屏蔽所有异常流程
	 * @param string $usrl
	 * @param int $startTimeStamp
	 * @param array $data
	 */
	private static function reportCostTime($appid,$mchInfo,$url, $startTimeStamp, $data){
		//如果不需要上报数据
		if(WechatPayConfig::REPORT_LEVENL == 0){
			return;
		} 
		//如果仅失败上报
		if(WechatPayConfig::REPORT_LEVENL == 1 &&
			 array_key_exists("return_code", $data) &&
			 $data["return_code"] == "SUCCESS" &&
			 array_key_exists("result_code", $data) &&
			 $data["result_code"] == "SUCCESS"){
		 	return;
		 }
		$endTimeStamp = self::getMillisecond();
		$input = array(
			"interface_url" => $url,
			"execute_time_" => $endTimeStamp - $startTimeStamp,
			"return_code" => isset($data["return_code"])?$data["return_code"]:'',
			"return_msg" => isset($data["return_msg"])?$data["return_msg"]:'',
			"result_code" => isset($data["result_code"])?$data["result_code"]:'',
			"err_code" => isset($data["err_code"])?$data["err_code"]:'',
			"err_code_des" => isset($data["err_code_des"])?$data["err_code_des"]:'',
			"out_trade_no" => isset($data["out_trade_no"])?$data["out_trade_no"]:'',
			"device_info" => isset($data["device_info"])?$data["device_info"]:''
		);
		self::report($appid,$mchInfo,$input);
	}
	/**
	 * 
	 * 测速上报，该方法内部封装在report中，使用时请注意异常流程
	 * @param WxPayReport $inputObj
	 * @param int $timeOut
	 * @throws WxPayException
	 * @return 成功时返回，其他抛异常
	 */
	private static function report($appid,$mchInfo,$input, $timeOut = 1)
	{
		$url = "https://api.mch.weixin.qq.com/payitil/report";
		$input['appid'] =  $appid;
		$input['mch_id'] =  $mchInfo['mchid'];
		$input['user_ip'] =  $_SERVER['REMOTE_ADDR'];
		$input['time'] =  date("YmdHis");
		$input['nonce_str'] =  Tools::createNonceStr();;

		$input['sign'] = self::MakeSign($input,$mchInfo);
		$xml = self::ToXml($input);
		
		$response = self::postXmlCurl($xml, $url, false, $timeOut,$mchInfo);
		return $response;
	}
	/**
	 * 获取毫秒级别的时间戳
	 */
	private static function getMillisecond()
	{
		//获取毫秒的时间戳
		$time = explode ( " ", microtime () );
		$time = $time[1] . ($time[0] * 1000);
		$time2 = explode( ".", $time );
		$time = $time2[0];
		return $time;
	}
	/**
	 * 生成签名
	 * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
	 */
	public static function MakeSign($data,$mchInfo)
	{
		ksort($data);
		$string = self::ToUrlParams($data);
		$string = $string . "&key=".$mchInfo['key'];
		$string = md5($string);
		$result = strtoupper($string);
		return $result;
	}
	/**
	 * 格式化参数格式化成url参数
	 */
	private static function ToUrlParams($data){
		$buff = "";
		foreach ($data as $k => $v){
			if($k != "sign" && $v != "" && !is_array($v)){
				$buff .= $k . "=" . $v . "&";
			}
		}
		$buff = trim($buff, "&");
		return $buff;
	}
	/**
	 * 输出xml字符
	 * @throws WxPayException
	**/
	private static function ToXml($data)
	{
		if(!is_array($data) || count($data) <= 0){
    		return false;
    	}
    	
    	$xml = "<xml>";
    	foreach ($data as $key=>$val)
    	{
    		if (is_numeric($val)){
    			$xml.="<".$key.">".$val."</".$key.">";
    		}else{
    			$xml.="<".$key."><![CDATA[".$val."]]></".$key.">";
    		}
        }
        $xml.="</xml>";
        return $xml; 
	}
	/**
     * 将xml转为array
     * @param string $xml
     * @throws WxPayException
     */
	private static function FromXml($xml)
	{	
		if(!$xml){
			return 50018;
		}
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);		
		return $data;
	}
	/**
	 * 
	 * 检测签名
	 */
	private static function CheckSign($data,$mchInfo)
	{
		if(!array_key_exists('sign', $data)){
			return false;
		}
		
		$sign = self::MakeSign($data,$mchInfo);
		if($data['sign'] == $sign){
			return true;
		}
		return false;
	}
	
    /**
     * 将xml转为array
     * @param string $xml
     * @throws WxPayException
     */
	private static function resolveRel($xml,$mchInfo){
		$data = self::FromXml($xml);
		if(is_numeric($data) || $data['return_code'] != 'SUCCESS'){
			 return $data;
		}
		if(self::CheckSign($data,$mchInfo)){
			return $data;
		}
        return 50019;
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