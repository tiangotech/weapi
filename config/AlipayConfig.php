<?php
/**
* 支付宝支付配置账号信息
*@author hedongji
*@since 2019-01-07 build
*/
namespace weapi\config;
class AlipayConfig
{
	const MTC = ['TEST'];
	const APP = ['APP'];
	//=======【TEST商户配置信息】==========================================
	public static $TEST = array(
		"gatewayUrl " => '',
		"notify_url" => '',
		"appId" => '',
		'seller_id' => '',
		"rsaPrivateKey" => '',
		"alipayrsaPublicKey" => ''
	);
	//=======================================================================
}
