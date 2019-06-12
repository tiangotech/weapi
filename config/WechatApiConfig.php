<?php
/**
* 支付配置账号信息
*@author hedongji
*@since 2019-01-07 build
*/
namespace weapi\config;
class WechatApiConfig
{
	const PLATFORM = ['TG_PUB'];

	//=======【天狗互动公众号】=====================================
	public static $TG_PUB_TOKEN = '';
	public static $TG_PUB_AESKEY = "";
	public static $TG_PUB_APPID = '';
	public static $TG_PUB_APPSECRET = '';
	//=======================================================================
	
	public static function getAppConfig($app){
		if (!in_array($app, self::PLATFORM)) {
			return false;
		}
		$appconfig = array();
		$appid = $app."_APPID";
		$secret = $app."_APPSECRET";
		if (!isset(self::$$appid)) {
			return false;
		}
		if (!isset(self::$$secret)) {
			return false;
		}

		$appconfig['APPID'] = self::$$appid;
		$appconfig['APPSECRET'] = self::$$secret;
		return $appconfig;
	}
}
