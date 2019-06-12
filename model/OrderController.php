<?php
/**
*订单处理类
*@author hedongji
*@since 2019-01-07 build
*/
namespace weapi\model;

defined('IN_API') or exit('Access Denied');
use weapi\config\WechatApiConfig;
use weapi\config\Config;
use weapi\config\WechatPayConfig;
use weapi\util\Tools;
class Order {
	/**
     * 创建订单
     * @access public
     * @param int     $uid 用户id
     * @param string  $pId 产品号
     * @return string  内部订单号
     */
	public static function buildOrder($uid,$pId){
		$orderinfo['productPrice'] = 0;
          $orderinfo['productDetail'] = "";
          $orderinfo['transactId'] = "";
          $orderinfo['productName'] = "";//产品名
          return $orderinfo;
	}

	/**
     * 查询订单
     * @access public
     * @param string  $outTradeNo 内部订单号
     * @return array  订单信息
     */
	public static function getOrder($tradeNo){
          $orderinfo['productPrice'] = 0;
          $orderinfo['productDetail'] = "";
          $orderinfo['transactId'] = "";
          $orderinfo['productName'] = "";//产品名
		return $orderinfo;
	}

	/**
     * 完成订单
     * @access public
     * @param string  $ordercodeout 外部订单号
     * @param string  $ordercodein 内部订单号
     * @param string  $openid 微信openid
     * @param string  $paywayid 支付途径
     * @param string  $source 订单来源
     * @return bool  
     */
	public static function sucOrder($ordercodeout,$ordercodein,$openid,$paywayid=3,$source="微信公众号"){
		return true;
	}
}