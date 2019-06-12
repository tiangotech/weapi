<?php
/**
* 支付配置账号信息
*@author hedongji
*@since 2019-01-07 build
*/
namespace weapi\config;
class WechatPayConfig
{
	const MTC = ['LDKJ'];

	//=======【商户配置信息】==========================================
	public static $LDKJ = array(
		"mchid" => '',
		"key" => '',
		"sslcert_path" => __DIR__.'/cert/LDKJ/apiclient_cert.pem',
		"sslckey_path" => __DIR__.'/cert/LDKJ/apiclient_key.pem'
	);
	//=======================================================================

	//=======================================================================
	const CURL_PROXY_HOST = "0.0.0.0";
	const CURL_PROXY_PORT = 1;
	/**
	 * TODO：接口调用上报等级，默认紧错误上报（注意：上报超时间为【1s】，上报无论成败【永不抛出异常】，
	 * 不会影响接口调用流程），开启上报之后，方便微信监控请求调用的质量，建议至少
	 * 开启错误上报。
	 * 上报等级，0.关闭上报; 1.仅错误出错上报; 2.全量上报
	 */
	const REPORT_LEVENL = 1;
	//=======================================================================
	//=====支付app和mch关联
	public static function payAppUnionMch($app,$tradetype="APP"){
		switch ($tradetype) {
			case 'JSAPI':
				$mchInfo = array(
					"TG_PUB" => "LDKJ",//六度空间
				);
				break;
			case 'APP':
				$mchInfo = array(
					"TG_APP" => "LDKJ",//六度空间
				);
				break;
			case 'MWEB':
				$mchInfo = array(
					"TG_PUB" => "LDKJ",//六度空间
				);
				break;
			case 'NATIVE':
				$mchInfo = array(
					"TG_PC" => "LDKJ",//六度空间
				);
				break;
		}
		$mch = isset($mchInfo[$app])?$mchInfo[$app]:"";
		if (empty($mch)) {
			return false;
		}
		return $mch;
	}
	//=====红包app和mch关联
	public static function sendhbAppUnionMch($app){
		$mchInfo = array(
					"TG_PUB" => "LDKJ"
				);
		$mch = isset($mchInfo[$app])?$mchInfo[$app]:"";
		return $mch;
	}
}
