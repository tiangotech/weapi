<?php
/**
* 支付宝支付配置账号信息
*@author hedongji
*@since 2019-01-07 build
*/
namespace weapi\config;
class OfficialpayConfig
{
	const MTC = ['HW','VIVO','MI','OPPO','IOS'];
	const APP = ['TEST_APP'];
	//=======【IOS商户配置信息】==========================================
	public static $IOS = array(
		"TEST_APP"=>array(
			"appId" => "",
			"sku" => "",
			"suitId" => ""
		)

	);
	//=======【MI商户配置信息】==========================================
	public static $MI = array(
		"TEST_APP"=>array(
			"appId" => "",
			"appsecret" => "",
			"appkey" => ""
		)
	);
	//=======【OPPO商户配置信息】==========================================
	public static $OPPO = array(
		"TEST_APP"=>array(
			"appId" => "",
			"appsecret" => "",
			"appkey" => "",
			"payPublicKey" => ""
		)
	);
	//=======================================================================
	//=======【VIVO商户配置信息】==========================================
	public static $VIVO = array(
		"TEST_APP"=>array(
			"appId" => "",
			"cpId" => "",
			"appkey" => ""
		)
	);
	//=======================================================================
	//=======【HW商户配置信息】==========================================
	public static $HW = array(
		"TEST_APP"=>array(
			"appId" => "",
			"appSecret" => "",
			"payID" => "",
			"cpId" => "",
			"payPrivateKey" => "",
			"payPublicKey" => ""

		)
	);
	//=======================================================================
}
