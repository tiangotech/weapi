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
use weapi\util\Tools;
use weapi\util\Log;
use weapi\util\DB;
class WechatLogin {
	public static $error_msg=40999;
	public static $log;
	public $db;
	/**
     * 根据code获取微信用户信息
     * @access public
     * @param string  $code          微信code
     * @param string  $app           应用 ['TG_PUB']
     * @param int  $platform         平台id 默认 0
     * @param string  $return_params 回调时需要获取的微信用户信息参数，$redirect_uri空时返回josn数据   默认：unionid-openid-headimgurl-nickname
     * @param string  $islogin       是否需要登录操作 0不需要，其他类型需要登录操作，可按需要扩展处理
     * @param string  $redirect_uri         回调地址
     * @param string  $version         版本号
     * @param string  $extra         扩展字段（json字串base64编码）
     */
	public static function getUserinfoByCode($code="",$app="TG_PUB",$platform=0,$return_params="unionid-openid-headimgurl-nickname",$islogin=0,$redirect_uri="",$version="0.0.0",$extra="",$mac1="",$mac2=""){
		self::$log = new Log("WechatLogin",30);
		if (empty($code)) {
			self::$log->critical("[app:".$app.",platform:".$platform."]code null");
			return 40010;
		}
		
		$appconfig = WechatApiConfig::getAppConfig($app);
		if ($appconfig === false) {
			return 40011;
		}
	    $appID = $appconfig["APPID"];
		$secret = $appconfig["APPSECRET"];

		$url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=".$appID."&secret=".$secret."&code=".$code."&grant_type=authorization_code";
		$rel = Tools::curlByPost($url);
		$body = json_decode($rel,true);
		if (isset($body['access_token'])) {
			$url = "https://api.weixin.qq.com/sns/userinfo?access_token=".$body['access_token']."&openid=".$body['openid']."&lang=zh_CN";
			$rel = Tools::curlGet($url);
			$userinfo = json_decode($rel,true);
			// if (isset($userinfo['unionid'])) {//unionid关联机制
			if (isset($userinfo['openid'])) {
				if ($islogin==0) {
					$params = array();
					foreach (explode("-", $return_params) as $par) {
						if (isset($userinfo[$par])) {
							$params[$par] = $userinfo[$par];
						}else{
							return 40011;
						}
					}
					if($redirect_uri){
						if($new_redirect_uri = self::getHttpRedrectUri($redirect_uri,$params)){
							header("Location:".$new_redirect_uri);die;
						}else{
							self::$log->critical("生成redirect_uri失败,redirect_uri=".$redirect_uri.",params=".json_encode($params,JSON_UNESCAPED_UNICODE));
							header("Location:"."./500.html");exit();
						}
					}else{
						return $params;
					}
				}else{
					$userinfo['openid'] = $body['openid'];
					$userinfo['nickname'] = Tools::removeEmoji($userinfo['nickname']);
					return self::wechatLoginCommon($userinfo,$platform,$extra,$islogin,$redirect_uri,$version,$mac1,$mac2);
				}
			}else{
				self::$log->critical("微信获取用户信息失败，原因：".$rel);
			}
		}else{
			self::$log->critical("微信登录获取access_token失败，原因：".json_encode($body,JSON_UNESCAPED_UNICODE));
		}
		return 40001;
	}
	/**
     * 内部登录处理
     */
	public static function wechatLoginCommon($userinfo,$platform,$extra,$islogin,$redirect_uri,$version,$mac1="",$mac2=""){
		if ($rel = self::Login($userinfo,$platform,$version,$mac1,$mac2)) {
			    if (empty($redirect_uri)) {
	    			//返回json数据
	    			return $rel;
			    }else{
			    	//携带参数回跳
				    $url = self::getHttpRedrectUri($redirect_uri,["param"=>"param"]);
				    header("Location: $url");die;
				}
		}else{
			if ($redirect_uri) {
				header("Location:"."./500.html");exit();
			}
			$error_msg = isset(self::$error_msg)?self::$error_msg:40999;
			return $error_msg;
		}
		    
		    
		    
	}
	/**
     * 内部登录
     * @access private
     * @param array  $userinfo       微信用户信息
     * @param int    $platform       平台id 默认 4098
     * @param string  $version       版本号
     */
	private static function Login($userinfo,$platform,$version='0.0.0',$mac1="",$mac2=""){
		return true;
	}
	private static function getHttpRedrectUri($redrect,$params){
    	$newRedrect = '';
    	if (empty($redrect)) {
    		return false;
    	}
    	$parse_url = parse_url($redrect);
    	if (!in_array($parse_url['scheme'], ['http','https'])) {
    		return false;
    	}
    	if (empty($parse_url['host'])) {
    		return false;
    	}
  		$newRedrect = $parse_url['scheme']."://".$parse_url['host'];
  		if (isset($parse_url['port'])) {
  			$newRedrect .= ":".$parse_url['port'];
  		}
  		if (isset($parse_url['path'])) {
  			$newRedrect .= $parse_url['path'];
  		}
  		if (isset($parse_url['fragment'])) {
  			$newRedrect .= "#".$parse_url['fragment'];
  		}
  		foreach ($params as $key => $value) {
		    if (isset($parse_url['query'])) {
	  			$parse_url['query'] .= "&".$key."=".$value;
	  		}else{
	  			$parse_url['query'] = $key."=".$value;
	  		}
		}
  		if (isset($parse_url['query'])) {
  			$newRedrect .= '?'.$parse_url['query'];
  		}
  		return $newRedrect;
    }
}