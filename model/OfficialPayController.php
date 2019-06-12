<?php  
/**
*huawei vivo oppo xiaomi 支付
*@author hedongji
*@since 2019-03-18 
*/
namespace weapi\model;

defined('IN_API') or exit('Access Denied');
use weapi\config\OfficialpayConfig;
use weapi\util\Tools;
use weapi\util\Log;
class OfficialPay {
	private $log;
	private $vivo_trade_url = "https://pay.vivo.com.cn/vcoin/trade";
	private $oppo_notify = "https://pubapi.nbgame.cn/weapi/officialpaynotify.php";
	private $vivo_notify = "https://pubapi.nbgame.cn/weapi/officialpaynotify.php";
	private $huawei_notify = "https://pubapi.nbgame.cn/weapi/officialpaynotify.php";
	//*所有方法都需要 $app (应用参数)
    //需要auth验证(必须$uid)  __construct($auth)
    public function __construct($auth){
    	$this->log = new Log("Officialpay",30);
    }
    /**
     * 支付
     * @access public
     * @param string  $productid     产品id
     * @param string  $app 			 应用['NBDZ','NBMJ','NBSK','CXDZ','NBDT']
     * @param int     $uid           用户id  
     * @param string  $mch         	 商户平台['HW','VIVO','MI','OPPO','IOS']
     * @param int     $platform      支付平台
     *小米支付需要开放平台配置回调
     *华为支付需要开放平台配置回调否则使用huawei_notify
     */
    public function pay($orderinfo,$app,$mch,$platform=''){
		//创建订单
		if (!in_array($app, OfficialpayConfig::APP)) return 50013;
		if (!in_array($mch, OfficialpayConfig::MTC)) return 50014;
		$mchInfo = $this->getMchInfo($app,$mch);
		$this->app = $app;
		$this->mch = $mch;

		$product_id = $orderinfo['productId'];//产品ID
		$transact_id = $orderinfo['transactId'];//订单号
		$fproductName = $orderinfo['productName'];//产品名
		$fproductPrice = $orderinfo['productPrice'];//产品价格
		$fproductDetail = $orderinfo['productDetail'];//产品描述
				switch ($mch) {
					case 'HW':
						$sign = $this->huwei_sign($mchInfo,$orderinfo);
						$result["sign"] = $sign;
						$result['url'] = $this->huawei_notify;
						$result["product_id"] = $product_id;
						$result["transact_id"] = $transact_id;
						$result["fproductName"] = $fproductName;
						$result["fproductPrice"] = $fproductPrice;
						$result["fproductDetail"] = $fproductDetail;
						$result["extReserved"] = base64_encode($app."-".$mch);
						break;
					case 'IOS':
						$sign = $this->ios_sign($mchInfo,$orderinfo);
						$result["sign"] = $sign;
						$result["product_id"] = $product_id;
						$result["transact_id"] = $transact_id;
						break;
					case 'VIVO':
						$rel = $this->vivo_sign($mchInfo,$orderinfo);
						if ($rel !== false) {
							$result["signature"] = $rel['signature'];
							$result["orderAmount"] = $rel['orderAmount'];
							$result["orderNumber"] = $rel['orderNumber'];
							$result["accessKey"] = $rel['accessKey'];
							$result["productName"] = $fproductName;
							$result["productDetail"] = $fproductDetail;
							$result["product_id"] = $product_id;
							$result["transact_id"] = $transact_id;
							$result["extInfo"] = base64_encode($app."-".$mch);
							
						}else{
							return "签名失败";
						}
						break;
					case 'MI':
						$result["product_id"] = $product_id;
						$result["transact_id"] = $transact_id;
						$result["fproductName"] = $fproductName;
						$result["fproductPrice"] = $fproductPrice;
						$result["fproductDetail"] = $fproductDetail;
						$result["cpUserInfo"] =  base64_encode($app."-".$mch);
						break;
					case 'OPPO':
						$result["product_id"] = $product_id;
						$result["transact_id"] = $transact_id;
						$result["fproductName"] = $fproductName;
						$result["fproductPrice"] = $fproductPrice;
						$result["fproductDetail"] = $fproductDetail;
						$result["callbackUrl"] =  $this->oppo_notify;
						$result["attach"] = base64_encode($app."-".$mch);
						break;
				}
				return $result;

	}
	
	/**
     * ios 完成订单
     * @access public
     * @param string  $receipt     	 苹果支付票据
     * @param string  $transactid    内部订单号
     * @param string  $app 			 应用['NBDZ','NBMJ','NBSK','CXDZ','NBDT']
     */
	public function iosTradeFinish($receipt,$transactid,$app){
		if (empty($receipt)) {
			return "未检测到IOS支付完成数据包";
		}
		if (empty($transactid)||empty($app)) {
				return "IOS购买产品获取订单信息失败";
		}else{
			$orderinfo = Order::getOrder($transactid);
			if (empty($orderinfo)) {
				return "系统不存在[ $transactid ]的订单";
			}
			//==========ios 票据验证==============
			$backreceipt = $this->verifyReceipt($receipt);
			//====================================
		}
		return $backreceipt
	}
	private function verifyReceipt($receipt){
		$strURL = "https://buy.itunes.apple.com/verifyReceipt";
		$receiptData = array("receipt-data"=>$receipt);
		//==========app stroe验证收据服务器较慢可能存在超时情况，需要优化====================
		$backAppStore = $this->sendPost($strURL,json_encode($receiptData));
		$backArr = json_decode($backAppStore,true);

		$backstatus = $backArr["status"];
		$backreceipt = isset($backArr["receipt"])?$backArr["receipt"]:[];
		
		if ($backstatus === 0) {
			return $backreceipt;
		}elseif($backstatus==21007){
			//**沙盒测试环境
			$sandBoxUrl = "https://sandbox.itunes.apple.com/verifyReceipt";
			$backAppStore = $this->sendPost($sandBoxUrl,json_encode($receiptData));
			$backArr = json_decode($backAppStore,true);
			$backstatus = $backArr["status"];
			$backreceipt = $backArr["receipt"];
			if ($backstatus === 0) {
				return $backreceipt;
			}else{
				return false;
			}
		}else{
			$this->log->critical("IOS购买产品验证失败，状态为：".$backstatus.",验证返回结果：".$backAppStore);
			return false;
		} 
	}
	public function sendPost($url,$data){
		$ch = curl_init ();
		curl_setopt ( $ch, CURLOPT_URL, $url );
		curl_setopt ( $ch, CURLOPT_POST, 1 );
		curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt($ch, CURLOPT_TIMEOUT, 30); 
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
		curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
		$return = curl_exec ( $ch );
		curl_close ( $ch );
		if($return === false){
			if(curl_errno($ch) == CURLE_OPERATION_TIMEDOUT){
				$this->log->critical("IOS购买产品验证超时，request body：".$data);
			}
		}
		return $return;
	}
	private function huwei_sign($mchInfo,$orderinfo){
		$arrOrderPost = array();
		$arrOrderPost['merchantId'] = $mchInfo['payID'];
		$arrOrderPost['applicationID'] = $mchInfo['appId'];
		$arrOrderPost['amount'] = $orderinfo['price'];
		$arrOrderPost['productName'] = $orderinfo['detail'];
		$arrOrderPost['requestId'] = $orderinfo['codein'];
		$arrOrderPost['productDesc'] = $orderinfo['detail'];
		$arrOrderPost['urlver'] = "2";
		$arrOrderPost['sdkChannel'] = 1;
		$arrOrderPost['url'] = $this->huawei_notify;
		ksort($arrOrderPost);reset($arrOrderPost);
		$arrOrderString = $this->getUrlQuery($arrOrderPost);

		$priKey = $mchInfo['payPrivateKey'];
		$priKey = "-----BEGIN RSA PRIVATE KEY-----\n" .
			wordwrap($priKey, 64, "\n", true) .
			"\n-----END RSA PRIVATE KEY-----";
	    $pkeyid = openssl_get_privatekey($priKey);
	    openssl_sign($arrOrderString, $sign, $pkeyid,OPENSSL_ALGO_SHA256);
	    openssl_free_key($pkeyid);
	    $sign = base64_encode($sign);
	    return $sign;
	}
	private function ios_sign($mchInfo,$orderinfo){
		$arrOrderPost = array();
		$arrOrderPost['appId'] = $mchInfo['appId'];
		$arrOrderPost['suitId'] = $mchInfo['suitId'];
		$arrOrderPost['sku'] = $mchInfo['sku'];
		$arrOrderPost['price'] = $orderinfo['price'] * 100;
		$arrOrderPost['code'] = $orderinfo['code'];
		$arrOrderPost['codein'] = $orderinfo['codein'];
		ksort($arrOrderPost);reset($arrOrderPost);
		$arrOrderString = $this->getUrlQuery($arrOrderPost);
		$signature = strtolower(md5($arrOrderString));
		return $signature;
	}
	private function vivo_sign($mchInfo,$orderinfo){
		$arrOrderPost = array();
		$arrOrderPost['version'] = "1.0.0";
		$arrOrderPost['appId'] = $mchInfo['appId'];
		$arrOrderPost['cpId'] = $mchInfo['cpId'];
		$arrOrderPost['cpOrderNumber'] = $orderinfo['codein'];
		$arrOrderPost['notifyUrl'] = $this->vivo_notify;
		$arrOrderPost['orderTime'] = date('YmdHis',time());
		$arrOrderPost['orderAmount'] = $orderinfo['price'] * 100;
		$arrOrderPost['orderTitle'] =  $orderinfo['detail'];
		$arrOrderPost['orderDesc'] =$orderinfo['detail'];
		$arrOrderPost['extInfo'] = base64_encode($this->app."-".$this->mch);
		ksort($arrOrderPost);reset($arrOrderPost);
		$arrOrderString = $this->getUrlQuery($arrOrderPost);
		$arrOrderString = $arrOrderString . '&' . strtolower(md5($mchInfo['appkey']));
		$arrOrderPost['signMethod'] = "MD5";
		$arrOrderPost['signature'] = strtolower(md5($arrOrderString));
		$ret = Tools::curlByPost($this->vivo_trade_url,$arrOrderPost);
		$rel = json_decode($ret,true);
		if ($rel['respCode'] != 200) {
			$this->log->critical("签名失败，返回数据包：".json_encode($ret));
			return false;
		}
		return $rel;
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
	private  function getUrlQuery($array_query){
	    $tmp = array();
	    foreach($array_query as $k=>$param)
	    {
	        $tmp[] = $k.'='.$param;
	    }
	    $params = implode('&',$tmp);
	    return $params;
	}
}