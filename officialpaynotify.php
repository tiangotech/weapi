<?php
//支付宝支付成功回调地址
namespace weapi;
require_once __DIR__ . '/autoload.php';
ini_set("display_errors", "On");
define('IN_API',true);
use weapi\util\Tools;
use weapi\config\OfficialpayConfig;
use weapi\config\PayConfig;
use weapi\model\Order;
use weapi\util\Log;

class Notify{
	private $result = true;
	//华为测试参数  $params = json_decode('{"amount":"2.00","orderId":"A1b3634987b502ac2f7f68a8a1156441","notifyTime":"1553669213024","extReserved":"VEVTVF9BUFAtSFc=","userName":"40086000000081405","accessMode":"0","productName":"2\u5143\u5b9d","result":"0","tradeTime":"2019-03-27 14:46:52","bankId":"AliPay","payType":"4","orderTime":"2019-03-27 14:46:51","spending":"0.00","requestId":"20190327144642-1641","signType":"RSA256","sign":"UJGFgpzEDk2TDjCaPQ9vdjkI5jPevE9MwUfccsoIOw63kaM\/yKVX3GDX5sXIVq0jkutZRti1tTLXuSVoq7JP5KwEvIzLvIn\/d+WPGJC\/0k6mwC0CZHckbL\/jC8lNaaVElVID6kftUylikL3OW2Mow+BClJyEVt\/j8KrYbk47CMpes7gItI7cneB8f8p5JeVS8dRRiOHSO1YLmLpklEqTZODvZZaaQYvsBOsdNhTOSPs0U09TmWq3iTesFC8AAxMXcCKIefVPI3j7jD3LtMhbOKHjdP4ke2spd\/8zyxu560UxW7rt\/hqOemrRMbGdySEjhbZCgEmBf1C12gbm8LO0YQ=="}',true);
	public function NotifyProcess(){
		$this->log = new Log("Officialpay",30,["1406835034@qq.com"]);
		$params = $this->notifyParams();
		if (empty($params)) {
			die;
		}
		$this->log->notice("支付回调参数：".json_encode($params));
		$rel = $this->appAndMch($params,$app,$mch);
		if ($rel == false) {
			$this->result = false;
			$this->log->critical("app和mch数据获取失败，支付回调参数：".json_encode($params));
			$this->niticePlatform($mch,false,3);
		}
		$mchInfo = $this->getMchInfo($app,$mch);
		if (empty($mchInfo)) {
			$this->result = false;
			$this->log->critical("没有商户配置信息，支付回调参数：".json_encode($params));
			$this->niticePlatform($mch,false,3);
		}
		$trade_no = $this->checkTrade($params,$mch);
		if ($trade_no) {
			//验签
			$check_rel = $this->checkSign($mch,$params,$mchInfo);
			if (!$check_rel) {
				$this->log->critical("验签失败，支付回调参数：".json_encode($params));
				$this->niticePlatform($mch,false,1);
			}
			$orderinfo = Order::getOrder($trade_no);
			if (empty($orderinfo)) {
				$this->log->critical("没有找到订单信息，支付回调参数：".json_encode($params));
			}
			if ($orderinfo['status']==0) {
				$notify_time = date("Y-m-d H:i:s",time());
				$source = $mch;
				$buyer_id = $this->getBuyerId($mch,$params);
				$paywayid = $this->getPaywayId($mch,$params);
				$trade_no_out = $this->getTradenoOut($mch,$params);
				//=====================完成订单业务处理========================


				//=============================================================
				if($isok){
					$this->log->notice("订单[".$trade_no."]完成");
				}else{
					$this->log->critical("内部完成订单失败，<br>请求参数：".json_encode($dataArr)."<br>返回结果：".json_encode($rel));
					$this->niticePlatform($mch,false,99);
				}
			}
			$this->niticePlatform($mch,true,0);
		}else{
			$this->log->critical("平台返回的订单状态为不成功，支付回调参数：".json_encode($params));
			$this->niticePlatform($mch,false,3);
		}
	}
	private function getBuyerId($mch,$params){
		switch ($mch) {
			case 'HW':
				return $params['userName'];
				break;
			case 'OPPO':
				return $params['userId'];
				break;
			case 'VIVO':
				return $params['uid'];
				break;
			case 'MI':
				return $params['uid'];
				break;
		}
	}
	private function getPaywayId($mch,$params){
		switch ($mch) {
			case 'HW':
				return 11;
				break;
			case 'OPPO':
				return 12;
				break;
			case 'VIVO':
				return 13;
				break;
			case 'MI':
				return 14;
				break;
		}
	}
	private function getTradenoOut($mch,$params){
		switch ($mch) {
			case 'HW':
				return $params['orderId'];
				break;
			case 'OPPO':
				return $params['notifyId'];
				break;
			case 'VIVO':
				return $params['orderNumber'];
				break;
			case 'MI':
				return $params['orderId'];
				break;
		}
	}
	private function checkSign($mch,$params,$mchInfo){
		switch ($mch) {
			case 'HW':
				return $this->hw_checksign($params,$mchInfo);
				break;
			case 'OPPO':
				return $this->oppo_checksign($params,$mchInfo);
				break;
			case 'VIVO':
				return $this->vivo_checksign($params,$mchInfo);
				break;
			case 'MI':
				return $this->mi_checksign($params,$mchInfo);
				break;
		}
    	return false;
	}
	private function hw_checksign($params,$mchInfo) {
		ksort($params);
		$sign = $params["sign"];
		$signType = $params["signType"];
		unset($params["sign"]);unset($params["signType"]);

		$i = 0;
		$params_str= '';
		foreach($params as $key=>$value){
		    if($key != "sign" && $key != "signType"){
		       $params_str .= ($i == 0 ? '' : '&').$key.'='.$value;
			}elseif ($key == "signType"){
				$signtype = $value;
			}
		    $i++;
		}
	    $pubKey = $mchInfo['payPublicKey'];

		$pkeyid = "-----BEGIN PUBLIC KEY-----\n" .
				wordwrap($pubKey, 64, "\n", true) .
				"\n-----END PUBLIC KEY-----";
		//读取公钥文件
		// $pubKey = file_get_contents($rsaPublicKeyFilePath);
		//转换为openssl格式密钥
		// $pkeyid = openssl_get_publickey($pubKey);
	    if ($pkeyid) {
	        if ($signType == "RSA256"){
	            $verify = openssl_verify($params_str,base64_decode($sign), $pkeyid,OPENSSL_ALGO_SHA256);
	        }else{
	            $verify = openssl_verify($params_str, base64_decode($sign), $pkeyid);
	        }
	     // openssl_free_key($pkeyid);
	    }
	    if($verify == 1){
	        return true;
	    }else{
	        return false;
	    }
	}
	private function oppo_checksign($params,$mchInfo) {
		$params_str = "notifyId={$params['notifyId']}&partnerOrder={$params['partnerOrder']}&productName={$params['productName']}&productDesc={$params['productDesc']}&price={$params['price']}&count={$params['count']}&attach={$params['attach']}";
		$pubKey = $mchInfo['payPublicKey'];
		$pkeyid = "-----BEGIN PUBLIC KEY-----\n" .
				wordwrap($pubKey, 64, "\n", true) .
				"\n-----END PUBLIC KEY-----";
		$verify = openssl_verify($params_str, base64_decode($params['sign']), $pkeyid);
		if($verify == 1){
	        return true;
	    }else{
	        return false;
	    }
	}
	private function vivo_checksign($params,$mchInfo) {
		$signArray = array();
		$signArray['appId'] = $params["appId"];
		$signArray['cpId'] = $params["cpId"];
		$signArray['cpOrderNumber'] = $params["cpOrderNumber"];
		$signArray['extInfo'] = $params["extInfo"];
		$signArray['orderAmount'] = $params["orderAmount"];
		$signArray['orderNumber'] = $params["orderNumber"];
		$signArray['payTime'] = $params["payTime"];
		$signArray['respCode'] = $params["respCode"];
		$signArray['respMsg'] = $params["respMsg"];
		$signArray['tradeStatus'] = $params["tradeStatus"];
		$signArray['tradeType'] = $params["tradeType"];
		$signArray['uid'] = $params["uid"];
		ksort($signArray);reset($signArray);
		$tmp = array();
	    foreach($signArray as $k=>$param){
	        $tmp[] = $k.'='.$param;
	    }
	    $signArrayString = implode('&',$tmp);
	    $appkey = $mchInfo['appkey'];
	    $signArrayString = $signArrayString . '&' . strtolower(md5($appkey));
		$signString = strtolower(md5($signArrayString));
		if($signString == $params['signature']){
	        return true;
	    }else{
	        return false;
	    }
	}
	private function mi_checksign($params,$mchInfo) {
		$signArray = array();
		$signArray['appId'] = $params["appId"];
		$signArray['cpOrderId'] = $params["cpOrderId"];
		$signArray['cpUserInfo'] = $params["cpUserInfo"];
		$signArray['orderId'] = $params["orderId"];
		$signArray['orderStatus'] = $params["orderStatus"];
		$signArray['payFee'] = $params["payFee"];
		$signArray['payTime'] = $params["payTime"];
		$signArray['productCode'] = $params["productCode"];
		$signArray['productCount'] = $params["productCount"];
		$signArray['productName'] = $params["productName"];
		$signArray['uid'] = $params["uid"];

		ksort($signArray);reset($signArray);
		$tmp = array();
		foreach($signArray as  $k => $param){
			$tmp[] = $k.'='.urldecode($param);
		}
	    $signArrayString = implode('&',$tmp);
	    $appsecret = $mchInfo['appsecret'];
	    $signString = hash_hmac("sha1",$signArrayString,$appsecret);
	    if($signString == $params['signature']){
	        return true;
	    }else{
	        return false;
	    }
	}
	/****
	result 操作结果。
	0: 表示成功
	1: 验签失败
	2: 超时
	3: 业务信息错误，比如订单不存在
	94: 系统错误
	95: IO 错误
	96: 错误的url
	97: 错误的响应
	98: 参数错误
	99: 其他错误
	****/
	private function niticePlatform($mch,$status,$result){
		switch ($mch) {
			case 'HW':
				if ($status) {
					echo "{\"result\":0}";;die;
				}else{
					echo "{\"result\":$result}";;die;
				}
				break;
			case 'OPPO':
				if ($status) {
					echo "result=OK&resultMsg=0";die;
				}else{
					echo "result=FAIL&resultMsg=$result";die;
				}
				break;
			case 'VIVO':
				if ($status) {
					echo "success";die;
				}else{
					echo "fail";die;
				}
				break;
			case 'MI':
				// const OK 					= 200;
				// const ERR_OrderId			= 1506;
				// const ERR_AppId				= 1515;
				// const ERR_UID				= 1516;
				// const ERR_Signature			= 1525;
				$res = array(0=>200,1=>1525,3=>1506,98=>1515,99=>1516);
				if ($status) {
					echo "{\"errcode\":".$res[$result] ."}";die;
				}else{
					echo "{\"errcode\":".$res[$result] ."}";die;
				}
				break;
		}
	}
	private function checkTrade($params,$mch){
		switch ($mch) {
			case 'HW':
				if ($params['result'] == 0) return $params['requestId'];
				break;
			case 'OPPO':
				if ($params['sign']) return $params['partnerOrder'];
				break;
			case 'VIVO':
				if ($params['tradeStatus'] == "0000") return $params['cpOrderNumber'];
				break;
			case 'MI':
				if ($params['orderStatus'] == "TRADE_SUCCESS") return $params['cpOrderId'];
				break;
		}
    	return false;
	}
	private function notifyParams(){
		$content = file_get_contents('php://input');
        if (empty($_POST) && false !== strpos($this->contentType(), 'application/json')) {
            $post = (array) json_decode($content, true);
        } else {
            $post = $_POST;
        }
        if (!empty($content) && empty($post)) {
        	parse_str($content,$post);
        }
        $param = array_merge($_GET, $post);
		return $param;
	}
    private function contentType(){
        $contentType = $_SERVER['CONTENT_TYPE'];
        if ($contentType) {
            if (strpos($contentType, ';')) {
                list($type) = explode(';', $contentType);
            } else {
                $type = $contentType;
            }
            return trim($type);
        }
        return '';
    }
    private function appAndMch($params,&$app="",&$mch=""){
    	if (isset($params['extReserved'])) {
    		$ext = $params['extReserved'];
    	}elseif(isset($params['attach'])){
    		$ext = $params['attach'];
    	}elseif(isset($params['extInfo'])){
    		$ext = $params['extInfo'];
    	}else{
    		$ext = $params['cpUserInfo'];
    	}
    	if (empty($ext)) {
    		return false;
    	}

    	base64_decode($ext);
    	$arr = explode('-', base64_decode($ext));

    	if (!isset($arr[0])||!isset($arr[1])) {
    		return false;
    	}
    	$app = $arr[0];
    	$mch = $arr[1];
    	return true;
    }
    private function getMchInfo($app,$mch){
		$mchInfos = OfficialpayConfig::$$mch;
		if (empty($mchInfos)) {
			return false;
		}
		$mchInfo = $mchInfos[$app];
		if (empty($mchInfo)){
			return false;
		}
		// foreach ($mchInfo as $va) {
		// 	if (empty($va)) {
		// 		# code...
		// 	}
		// }
		return $mchInfo;
	}
}

$notify = new Notify();
$notify->NotifyProcess();