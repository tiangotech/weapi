<?php
/**
*支付宝支付类
*@author hedongji
*@since 2019-02-17 build
*/
namespace weapi\model;

defined('IN_API') or exit('Access Denied');
use weapi\config\AlipayConfig;
use weapi\util\Tools;
use weapi\util\Log;
class AliPay {
	public static $log;
	//应用ID
	public $appId;
	//私钥文件路径
	public $rsaPrivateKeyFilePath;
	//私钥值
	public $rsaPrivateKey;
	//网关
	public $gatewayUrl = "https://openapi.alipay.com/gateway.do";
	//返回数据格式
	public $format = "json";
	//api版本
	public $apiVersion = "1.0";
	// 表单提交字符集编码
	public $postCharset = "UTF-8";
	//使用文件读取文件格式，请只传递该值
	public $alipayPublicKey = null;
	//使用读取字符串格式，请只传递该值
	private $bizContent;
	private $apiParas = array();
	public $alipayrsaPublicKey;
	public $debugInfo = false;
	private $fileCharset = "UTF-8";
	private $RESPONSE_SUFFIX = "_response";
	private $ERROR_RESPONSE = "error_response";
	private $SIGN_NODE_NAME = "sign";
	//加密XML节点名称
	private $ENCRYPT_XML_NODE_NAME = "response_encrypted";
	private $needEncrypt = false;
	//签名类型
	public $signType = "RSA";
	//加密密钥和类型
	public $encryptKey;
	public $encryptType = "AES";

	//*所有方法都需要 $app (应用参数)
    //需要auth验证(必须$uid)  __construct($auth)
    public function __construct(){
    }
	/**
     * 支付
     * @access public
     * @param string  $orderinfo     
     * @param string  $app     		 应用 ['BYWH']
     * @param string  $tradetype     支付方式 ['APP','MWEB','PCWEB']
     * @param string  $mch         	 商户平台['GXBY','BX']
     * @param int     $platform      支付平台
     * @param string  $return_url    支付完成回跳地址
     */
	public function pay($orderinfo,$app,$tradetype,$mch,$platform,$return_url=""){
		self::$log = new Log("AliPay",30);

		$price = intval($orderinfo['productPrice']);
		$detail = $orderinfo['productDetail'];
		$codein = $orderinfo['transactId'];
		$subject = $orderinfo['productName'];//产品名
		if (strlen($detail) > 256) {
			$detail = mb_substr($detail,0,80,"utf-8");
		}
		if (!in_array($tradetype, ['APP','MWEB','PCWEB'])) return 50015;
		if (!in_array($app, AlipayConfig::APP)) return 50013;
		if (!in_array($mch, AlipayConfig::MTC)) return 50014;
		$mchInfo = AlipayConfig::$$app;
		//============统一支付参数数据=========================
		$this->gatewayUrl = 'https://openapi.alipay.com/gateway.do';
		$this->appId = $mchInfo['appId'];
		$this->rsaPrivateKey = $mchInfo['rsaPrivateKey'];
		$this->alipayrsaPublicKey = $mchInfo['alipayrsaPublicKey'];
		$this->apiVersion = '1.0';
		$this->postCharset='UTF-8';
		$this->format='json';
		$this->signType='RSA2';
		$passback_params = urlencode(http_build_query(array("app"=>$app,"mch"=>$mch)));
				
		$this->return_url = $return_url;
		//======================================================
		self::$log->notice("平台[".$platform."]发起[".$tradetype."]支付宝支付(商户".$mch.")，内部订单号[".$codein."]");
		switch ($tradetype) {
					case 'APP':
						$this->setBizContent("{" .
							"    \"body\":\"$detail\"," .
							"    \"subject\":\"$subject\"," .
							"    \"out_trade_no\":\"$codein\"," .
							"    \"timeout_express\":\"90m\"," .
							"    \"total_amount\":$price," .
							"    \"product_code\":\"QUICK_MSECURITY_PAY\"," .
							"    \"passback_params\":\"$passback_params\"" .
							"  }");
						$method = 'alipay.trade.app.pay';
						$result = $this->sdkExecute($method);
						return array("orderStr"=>$result);
						break;
					case 'MWEB':
						$this->setBizContent("{" .
							"    \"body\":\"$detail\"," .
							"    \"subject\":\"$subject\"," .
							"    \"out_trade_no\":\"$codein\"," .
							"    \"timeout_express\":\"90m\"," .
							"    \"total_amount\":$price," .
							"    \"product_code\":\"QUICK_WAP_WAY\"," .
							"    \"passback_params\":\"$passback_params\"" .
							"  }");
						$method = 'alipay.trade.wap.pay';
						$result = $this->pageExecute($method);
						echo $result;die;
						break;
					case 'PCWEB':
						$this->setBizContent("{" .
							"    \"body\":\"$detail\"," .
							"    \"subject\":\"$subject\"," .
							"    \"out_trade_no\":\"$codein\"," .
							"    \"timeout_express\":\"90m\"," .
							"    \"total_amount\":$price," .
							"    \"product_code\":\"FAST_INSTANT_TRADE_PAY\"," .
							"    \"passback_params\":\"$passback_params\"" .
							"  }");
						$method = 'alipay.trade.page.pay';
						$result = $this->pageExecute($method);
						echo $result;die;
						break;
		}
		return 50996;

	}
	private function setBizContent($bizContent){
		$this->biz_content = $bizContent;
	}
	/*
		页面提交执行方法
		@param：跳转类接口的request; $httpmethod 提交方式。两个值可选：post、get
		@return：构建好的、签名后的最终跳转URL（GET）或String形式的form（POST）
	*/
	public function pageExecute($method,$httpmethod = "POST") {
		$this->setupCharsets($this->biz_content);
		if (strcasecmp($this->fileCharset, $this->postCharset)) {
			self::$log->critical("文件编码：[" . $this->fileCharset . "] 与表单提交编码：[" . $this->postCharset . "]两者不一致!");
		}
		$iv=null;
		$iv=$this->apiVersion;

		$notify_url = $this->getHttpHost();
		if ($notify_url === false) return "notify_url is error";
		//组装系统参数
		$sysParams["app_id"] = $this->appId;
		$sysParams["method"] = $method;//'alipay.trade.wap.pay';
		$sysParams["format"] = $this->format;
		$sysParams["return_url"] = $this->return_url;
		$sysParams["notify_url"] = $notify_url;
		$sysParams["charset"] = $this->postCharset;
		$sysParams["sign_type"] = $this->signType;
		$sysParams["sign"] = "";
		$sysParams["timestamp"] = date("Y-m-d H:i:s");
		$sysParams["version"] = $iv;
		$sysParams['biz_content'] = "";
		

		if ($this->needEncrypt){
			$sysParams["encrypt_type"] = $this->encryptType;
			if ($this->checkEmpty($this->biz_content)) {
				self::$log->critical(" api request Fail! The reason : encrypt request is not supperted!");
			}
			if ($this->checkEmpty($this->encryptKey) || $this->checkEmpty($this->encryptType)) {
				self::$log->critical(" encryptType and encryptKey must not null! ");
			}
			if ("AES" != $this->encryptType) {
				self::$log->critical("加密类型只支持AES");
			}
			// 执行加密
			$enCryptContent = encrypt($this->biz_content, $this->encryptKey);
			$this->biz_content = $enCryptContent;
		}

		$sysParams['biz_content'] = $this->biz_content;
		$totalParams = $sysParams;
		//待签名字符串
		$preSignStr = $this->getSignContent($totalParams);
		//签名
		$totalParams["sign"] = $this->generateSign($totalParams, $this->signType);
		if ("GET" == strtoupper($httpmethod)) {
			//value做urlencode
			$preString=$this->getSignContentUrlencode($totalParams);
			//拼接GET请求串
			$requestUrl = $this->gatewayUrl."?".$preString;
			return $requestUrl;
		} else {
			//拼接表单字符串
			return $this->buildRequestForm($totalParams);
		}
	}
	/**
     * 生成用于调用收银台SDK的字符串
     * @param $request SDK接口的请求参数对象
     * @return string 
     * @author guofa.tgf
     */
	public function sdkExecute($method) {
		$this->setupCharsets($this->biz_content);
		$params['app_id'] = $this->appId;
		$params['method'] = $method;
		$params['format'] = $this->format; 
		$params['sign_type'] = $this->signType;
		$params['timestamp'] = date("Y-m-d H:i:s");
		$params['alipay_sdk'] = $this->alipayrsaPublicKey;
		$params['charset'] = $this->postCharset;
		$params['version'] = $this->apiVersion;
		$params['notify_url'] = $this->notify_url;
		$params['biz_content'] = $this->biz_content;
		ksort($params);
		$params['sign'] = $this->generateSign($params, $this->signType);
		foreach ($params as &$value) {
			$value = $this->characet($value, $params['charset']);
		}
		// print_r($params);die;
		return http_build_query($params);
	}
	//此方法对value做urlencode
	public function getSignContentUrlencode($params) {
		ksort($params);
		$stringToBeSigned = "";
		$i = 0;
		foreach ($params as $k => $v) {
			if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
				// 转换成目标字符集
				$v = $this->characet($v, $this->postCharset);
				if ($i == 0) {
					$stringToBeSigned .= "$k" . "=" . urlencode($v);
				} else {
					$stringToBeSigned .= "&" . "$k" . "=" . urlencode($v);
				}
				$i++;
			}
		}
		unset ($k, $v);
		return $stringToBeSigned;
	}
	/**
     * 建立请求，以表单HTML形式构造（默认）
     * @param $para_temp 请求参数数组
     * @return 提交表单HTML文本
     */
	protected function buildRequestForm($para_temp) {
		
		$sHtml = "<form id='alipaysubmit' name='alipaysubmit' action='".$this->gatewayUrl."?charset=".trim($this->postCharset)."' method='POST'>";
		while (list ($key, $val) = each ($para_temp)) {
			if (false === $this->checkEmpty($val)) {
				//$val = $this->characet($val, $this->postCharset);
				$val = str_replace("'","&apos;",$val);
				//$val = str_replace("\"","&quot;",$val);
				$sHtml.= "<input type='hidden' name='".$key."' value='".$val."'/>";
			}
        }
		//submit按钮控件请不要含有name属性
        $sHtml = $sHtml."<input type='submit' value='ok' style='display:none;''></form>";
		
		$sHtml = $sHtml."<script>document.forms['alipaysubmit'].submit();</script>";
		
		return $sHtml;
	}
	public function getSignContent($params) {
		ksort($params);
		$stringToBeSigned = "";
		$i = 0;
		foreach ($params as $k => $v) {
			if (false === $this->checkEmpty($v) && "@" != substr($v, 0, 1)) {
				// 转换成目标字符集
				$v = $this->characet($v, $this->postCharset);
				if ($i == 0) {
					$stringToBeSigned .= "$k" . "=" . "$v";
				} else {
					$stringToBeSigned .= "&" . "$k" . "=" . "$v";
				}
				$i++;
			}
		}
		unset ($k, $v);
		return $stringToBeSigned;
	}
	public function generateSign($params, $signType = "RSA") {
		return $this->sign($this->getSignContent($params), $signType);
	}
	protected function sign($data, $signType = "RSA") {
		if($this->checkEmpty($this->rsaPrivateKeyFilePath)){
			$priKey=$this->rsaPrivateKey;
			$res = "-----BEGIN RSA PRIVATE KEY-----\n" .
				wordwrap($priKey, 64, "\n", true) .
				"\n-----END RSA PRIVATE KEY-----";
		}else {
			$priKey = file_get_contents($this->rsaPrivateKeyFilePath);
			$res = openssl_get_privatekey($priKey);
		}
		($res) or self::$log->critical('您使用的私钥格式错误，请检查RSA私钥配置'); 
		if ("RSA2" == $signType) {
			openssl_sign($data, $sign, $res, OPENSSL_ALGO_SHA256);
		} else {
			openssl_sign($data, $sign, $res);
		}
		if(!$this->checkEmpty($this->rsaPrivateKeyFilePath)){
			openssl_free_key($res);
		}
		$sign = base64_encode($sign);
		return $sign;
	}
	/**
	 * 转换字符集编码
	 * @param $data
	 * @param $targetCharset
	 * @return string
	 */
	private function characet($data, $targetCharset) {
		if (!empty($data)) {
			$fileType = $this->fileCharset;
			if (strcasecmp($fileType, $targetCharset) != 0) {
				$data = mb_convert_encoding($data, $targetCharset, $fileType);
			}
		}
		return $data;
	}
	private function setupCharsets($request) {
		if ($this->checkEmpty($this->postCharset)) {
			$this->postCharset = 'UTF-8';
		}
		$str = preg_match('/[\x80-\xff]/', $this->appId) ? $this->appId : print_r($request, true);
		if (function_exists('mb_detect_encoding')) {
			$this->fileCharset = mb_detect_encoding($str, "UTF-8, GBK") == 'UTF-8' ? 'UTF-8' : 'GBK';
		}
	}
	/**
	 * 校验$value是否非空
	 *  if not set ,return true;
	 *    if is null , return true;
	 **/
	protected function checkEmpty($value) {
		if (!isset($value))
			return true;
		if ($value === null)
			return true;
		if (trim($value) === "")
			return true;

		return false;
	}
	private function getHttpHost(){
		$http = "http://";
		if ($_SERVER['SERVER_PORT'] == 443) {
			$http = "https://";
		}
		$request_uri = explode("?", $_SERVER['REQUEST_URI']);

		$notify =  $http.$_SERVER['HTTP_HOST'].$request_uri[0]."alipaynotify.php";
		$array = get_headers($notify,1);
		if(preg_match('/200/',$array[0])){
			return $notify;
		}else{
			return false;
		}
	}
}