<?php
//微信支付成功回调地址
namespace weapi;
require_once __DIR__ . '/autoload.php';
ini_set("display_errors", "On");
define('IN_API',true);

use weapi\util\Tools;
use weapi\config\PayConfig;
use weapi\util\Log;

class Notify{
	private $values;
	private $needSign = true;  //是否需要签名输出
	private $errorMsg = "error";

	public function NotifyProcess(){
		$this->log = new Log("WechatPay",30);
		$xml = file_get_contents('php://input');
		$content = Tools::FromXml($xml);
		// $content = json_decode('{"appid":"wx1d649e2006d2b01e","bank_type":"CFT","cash_fee":"200","device_info":"FHBY","fee_type":"CNY","is_subscribe":"Y","mch_id":"1511005391","nonce_str":"sxgZkxPSFuLj1Tuk","openid":"ojVGcxD8tkXNWw0zXaFqVOqBbQm8","out_trade_no":"20190122141328-1783","result_code":"SUCCESS","return_code":"SUCCESS","sign":"478C54D5B96288FA7D20C53B3526CA8E","time_end":"20190122141332","total_fee":"200","trade_type":"JSAPI","transaction_id":"4200000262201901228877945612"}',true);
		$mchInfo = array();
		if (isset($content) && is_array($content)) {
			$mch = $content['device_info'];
			$mchInfo = PayConfig::$$mch;
			//验签
			if (!$this->CheckSign($content,$mchInfo)) {
				$this->errorMsg = "微信支付回调验签失败";
				$this->log->critical("微信支付回调验签失败,回调参数：".json_encode($content,JSON_UNESCAPED_UNICODE));
			}else{
				if (!empty($content) && $content['result_code'] === "SUCCESS" && $content['return_code'] === "SUCCESS") {
					$transaction_id = $content['transaction_id'];
					$trade_no = $content['out_trade_no'];
					//=====================完成订单业务处理========================


					//=============================================================
				}else{
					$this->errorMsg = isset($content['err_code_des'])?$content['err_code_des']:"微信系统错误";
					$this->log->critical("微信支付回调,微信系统错误。参数：".json_encode($content,JSON_UNESCAPED_UNICODE));
				}
			}
		}else{
			$this->errorMsg = "微信系统错误";
		}
		//==============业务处理完成=====================
		if($isok){
			if (file_exists(__DIR__.'/log/QRimg/'.$trade_no.".png")) {
				if(!unlink(__DIR__.'/log/QRimg/'.$trade_no.".png")){
					$this->log->alert("订单[".$content["out_trade_no"]."],扫码支付完成，删除文件失败");
				}
			}
			$this->log->notice("订单[".$content["out_trade_no"]."]完成");
			$this->values['return_code'] = 'SUCCESS';
			$this->values['return_msg'] = 'OK';
		}else{
			$this->values['return_code'] = 'FAIL';
			$this->values['return_msg'] = $this->errorMsg;
			$this->needSign = false;
		}
		$this->ReplyNotify($mchInfo);
	}

	private function ReplyNotify($mchInfo=array()){
		//如果需要签名
		if($this->needSign == true && $this->values['return_code'] == "SUCCESS"){
			$this->values['sign'] = self::MakeSign($this->values,$mchInfo);
		}
		echo self::ToXml($this->values);
	}
	/**
	 * 
	 * 检测签名
	 */
	private static function CheckSign($data,$mchInfo){
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
	 * 生成签名
	 * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
	 */
	private static function MakeSign($data,$mchInfo){
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
	private static function ToXml($data){
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
}

$notify = new Notify();
$notify->NotifyProcess();
