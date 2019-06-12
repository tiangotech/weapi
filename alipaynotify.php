<?php
//支付宝支付成功回调地址
namespace weapi;
require_once __DIR__ . '/autoload.php';
ini_set("display_errors", "On");
define('IN_API',true);

use weapi\util\Tools;
use weapi\config\AlipayConfig;
use weapi\model\Order;
use weapi\model\AliPay;
use weapi\util\Log;

class Notify{
	private $values;
	private $needSign = true;  //是否需要签名输出
	private $errorMsg = "error";
	private $fileCharset = "UTF-8";
	private $postCharset = "UTF-8";
	public $format = "json";
	public $signtype = "RSA2";
	private $alipayPublicKey = "";

	public function NotifyProcess(){
		$this->log = new Log("AliPay",30);

		$content = $_POST;
		$mchInfo = array();
		$trade_status  = isset($content['trade_status'])?$content['trade_status']:"";//TRADE_SUCCESS
		if ($trade_status=="TRADE_SUCCESS"||$trade_status=="TRADE_FINISHED") {
			$this->signtype = $content['sign_type'];
			parse_str(urldecode($content['passback_params']),$passback_params);
			$mch = $passback_params['mch'];
			$app = $passback_params['app'];
			$mchInfo = AlipayConfig::$$app;
			$this->alipayPublicKey = $mchInfo['alipayrsaPublicKey'];
			//验签
			$check_rel = $this->check($content);
			if (!$check_rel) {
				$this->errorMsg = "支付宝支付回调验签失败";
				$this->log->critical("支付宝支付回调验签失败,回调参数：".json_encode($content,JSON_UNESCAPED_UNICODE));
			}else{
				$out_trade_no = $content['out_trade_no'];
				$trade_no = $content['trade_no'];
				$total_amount = $content['total_amount'];
				$seller_id = $content['seller_id'];
				$app_id = $content['app_id'];
				$rel = array();

				if ($seller_id==$mchInfo['seller_id']&&$app_id==$mchInfo['appId']) {
					$orderinfo = Order::getOrder($out_trade_no);
					if (empty($orderinfo)) {
						$rel['return'] = 0;
						$this->log->critical("支付宝支付回调获取[".$out_trade_no."]订单信息失败,回调参数：".json_encode($content,JSON_UNESCAPED_UNICODE));
					}else{
						if ($orderinfo['status']==0) {
							if ($total_amount != $orderinfo['price']) {
								$rel['return'] = 0;
								$this->log->critical("支付宝支付回调,回调参数total_amount(".$total_amount.")与内部订单[".$out_trade_no."] price (".$orderinfo['price'].")不一致,存在人为恶意串改风险。数据包：".json_encode($content,JSON_UNESCAPED_UNICODE));
							}else{
								//=====================完成订单业务处理========================


								//=============================================================
							}
						}else{
							$rel['return'] = 1;
						}
					}
				}else{
					$rel['return'] = 0;
					$this->log->critical("支付宝支付回调seller_id和app_id与系统配置不一样,回调参数：".json_encode($content,JSON_UNESCAPED_UNICODE));
				}
			}
		}else{
			if ($trade_status!="TRADE_CLOSED") {
				$this->log->critical("支付宝返回订单状态：$trade_status");
			}
		}
		if($isok){
			$this->log->notice("订单[".$content["out_trade_no"]."]完成");
			$return_msg = 'success';
			echo $return_msg;die;
		}else{
			echo "fail";die;
		}
		
	}
	/**
	 * 验签方法
	 * @param $arr 验签支付宝返回的信息，使用支付宝公钥。
	 * @return boolean
	 */
	public function check($arr,$alipay_public_path=""){
		$result = $this->rsaCheckV1($arr, $alipay_public_path, $this->signtype);
		return $result;
	}
	/** rsaCheckV1 & rsaCheckV2
	 *  验证签名
	 *  在使用本方法前，必须初始化AopClient且传入公钥参数。
	 *  公钥是否是读取字符串还是读取文件，是根据初始化传入的值判断的。
	 **/
	public function rsaCheckV1($params, $rsaPublicKeyFilePath,$signType='RSA') {
		$sign = $params['sign'];
		$params['sign_type'] = null;
		$params['sign'] = null;
		return $this->verify($this->getSignContent($params), $sign, $rsaPublicKeyFilePath,$signType);
	}
	public function verify($data, $sign, $rsaPublicKeyFilePath, $signType = 'RSA') {
		if(!$this->checkEmpty($this->alipayPublicKey)){
			$pubKey = $this->alipayPublicKey;
			$res = "-----BEGIN PUBLIC KEY-----\n" .
				wordwrap($pubKey, 64, "\n", true) .
				"\n-----END PUBLIC KEY-----";
		}else {
			//读取公钥文件
			$pubKey = file_get_contents($rsaPublicKeyFilePath);
			//转换为openssl格式密钥
			$res = openssl_get_publickey($pubKey);
		}
		($res) or die('支付宝RSA公钥错误。请检查公钥文件格式是否正确');  
		//调用openssl内置方法验签，返回bool值
		$result = FALSE;
		if ("RSA2" == $signType) {
			$result = (openssl_verify($data, base64_decode($sign), $res, OPENSSL_ALGO_SHA256)===1);
		} else {
			$result = (openssl_verify($data, base64_decode($sign), $res)===1);
		}
		if($this->checkEmpty($this->alipayPublicKey)) {
			//释放资源
			openssl_free_key($res);
		}
		return $result;
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
	/**
	 * 转换字符集编码
	 * @param $data
	 * @param $targetCharset
	 * @return string
	 */
	public function characet($data, $targetCharset) {
		
		if (!empty($data)) {
			$fileType = $this->fileCharset;
			if (strcasecmp($fileType, $targetCharset) != 0) {
				$data = mb_convert_encoding($data, $targetCharset, $fileType);
				//				$data = iconv($fileType, $targetCharset.'//IGNORE', $data);
			}
		}


		return $data;
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
}

$notify = new Notify();
$notify->NotifyProcess();