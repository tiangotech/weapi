<?php
/**
*代替授权公众号接收和处理消息
*@author hedongji
*@since 2019-01-07 build
*/
namespace weapi\model;

defined('IN_API') or exit('Access Denied');
use weapi\config\WechatApiConfig;
use weapi\model\WechatApi;
use weapi\util\Tools;
use weapi\util\Log;
use weapi\util\DB;
class WechatCustom {
	public $log;
	public $app;
    public $itemTpl;
    public $db;

	public function index($app){
		if (!in_array($app, WechatApiConfig::PLATFORM)) {
			return 40011;
		}
		$this->log = new Log("WechatCustom",30);
		$this->app = $app;
	    if (isset($_GET['echostr'])) { 
	        $this->valid();  
	    }else{
	        $this->responseMsg();
	    }
	}
	private function valid(){
        $echoStr = @$_GET["echostr"];
        if($this->checkSignature()){
            echo $echoStr;
            exit;
        }else{
        	$this->log->critical('TcheckSignature false!');
        }
	}
	//服务器配置token验证
    private function checkSignature(){  
    	$tokenKey = $this->app.'_TOKEN';
        if (!isset(WechatApiConfig::$$tokenKey)) {  
            $this->log->critical('TOKEN is not defined!');
        }
        // $this->log->critical($tokenKey);
        $signature = @$_GET["signature"];  
        $timestamp = @$_GET["timestamp"];  
        $nonce = @$_GET["nonce"];  
        $token = WechatApiConfig::$$tokenKey;  
        $tmpArr = array($token, $timestamp, $nonce);  
        sort($tmpArr, SORT_STRING);  
        $tmpStr = implode($tmpArr);  
        $tmpStr = sha1($tmpStr);  
        if( $tmpStr == $signature ){  
            return true;  
        }else{  
            return false;  
        }  
    }
    private function responseMsg(){
      	$xml = file_get_contents('php://input');
		$this->postArr = Tools::FromXml($xml);   
      	if (!empty($this->postArr)){   
          	$this->postArr['time'] = time();
          	$this->itemTpl = "<xml><ToUserName><![CDATA[%s]]></ToUserName><FromUserName><![CDATA[%s]]></FromUserName><CreateTime>%s</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA[%s]]></Content></xml>"; 
	        //事件
	        if($this->postArr['MsgType'] == 'event') {
	            if ($this->postArr['Event'] == 'subscribe') {
	                $this->subscribe();//订阅事件
	            }
                if ($this->postArr['Event'] == 'unsubscribe') {
                    $this->unsubscribe();//取消订阅事件
                }
                if ($this->postArr['Event'] == 'LOCATION') {
                    $this->location();//上报地理位置事件
                }
                if ($this->postArr['Event'] == 'CLICK') {
                    $this->click();//点击菜单拉取消息时的事件推送
                }
                if ($this->postArr['Event'] == 'VIEW') {
                    $this->view();//点击菜单跳转链接时的事件推送
                }
                if ($this->postArr['Event'] == 'SCAN') {
                    $this->scan();//用户已关注时的事件推送
                }
	        }
          	//文本消息
          	if($this->postArr['MsgType'] == 'text'){  
	             $this->text();
        	}
            echo "SUCCESS";die; 
      	}
    }
    /**
	* 微信公众号订阅事件
	* @access private
    * @param int  $this->app  应用(微信公众号),可根据不同应用处理不同业务
    */
    private function subscribe(){
        $wxapi = new WechatApi($this->app);
        $openid = $this->postArr['FromUserName'];
        $userinfo = $wxapi->getUserinfo($openid);
        //{"subscribe":1,"openid":"oSFrb5kdARE35V5i3Ufc04Hf6YwI","nickname":"\u6696\u5fc3\u56e7","sex":1,"language":"zh_CN","city":"\u6210\u90fd","province":"\u56db\u5ddd","country":"\u4e2d\u56fd","headimgurl":"http:\/\/thirdwx.qlogo.cn\/mmopen\/mibmZSj2eTlAclI4O9dDwdh7It3kZytzjxqjlLCmjHAZBFicrSiaLzWN1JlIsUwliaq1Eic1vibe7hibY8LPnzCTOGdCKf3K68QYolb\/132","subscribe_time":1559751096,"unionid":"oeTSQ5i4piZHNrzoZ2pyZ_Xci9x0","remark":"","groupid":0,"tagid_list":[],"subscribe_scene":"ADD_SCENE_PROFILE_LINK","qr_scene":0,"qr_scene_str":""}
    }
    /**
    * 微信公众号取消订阅事件
    * @access private
    * @param int  $this->app  应用(微信公众号),可根据不同应用处理不同业务
    */
    private function unsubscribe(){
        // $this->log->critical(json_encode($userinfo));
        //{"subscribe":0,"openid":"oSFrb5vptdt6O7vy9w2-QrN5P_34","tagid_list":[]}
    }
    private function scan(){
        $wxapi = new WechatApi($this->app);
        $openid = $this->postArr['FromUserName'];
        $userinfo = $wxapi->getUserinfo($openid);
        //===============根据ticket匹配推广人===================

        //======================================================
        //=============推广海报=================================
        if (preg_match('|^invite_(.*+)$|', $this->postArr['EventKey'], $matches)) {
            $agenid_openid = $matches[1];//推广人的openid
            $agenid_userinfo = $wxapi->getUserinfo($agenid_openid);
            if ($openid == $agenid_openid) {
                return false;
            }
        }
    }
    /**
	* 接收用户发送的在微信公众号消息，处理不同业务
	* @access private
    * @param int  $this->app  应用(微信公众号),可根据不同应用处理不同业务
    * @param string $content  接收的消息内容
    */
    private function text(){
    	$content = $this->postArr['Content'];
    	//=============添加消息功能=============
        //1.推广海报
        if ($content=="推广海报") {
            $this->getPoster(addslashes($content));
        }
    	//2.红包兑换
    	if (strlen($content) == 10) {
    		$this->getHb(addslashes($content));
    	}
    	//3.获取openid
    	if ($content == "getopenid") {
    		$this->getOpenid();
    	}
    	//4.获取AccessToken
    	if ($content == "gettoken") {
    		$this->getAccessToken();
    	}
    	//n.普通处理
    	if ($content) {
	    	$this->respoense($content);
	    }
	    echo "SUCCESS";die; 
    }
    private function getPoster($content){
        $wxapi = new WechatApi($this->app);
        switch ($this->app) {
            case 'JQ'://借权推广海报
                $media_id = $wxapi->buildDiyPoster();
                break;
            default:
                $media_id = $wxapi->buildPoster();
                break;
        }
        
        if ($media_id == false) {
            $this->itemTpl = "<xml><ToUserName><![CDATA[%s]]></ToUserName><FromUserName><![CDATA[%s]]></FromUserName><CreateTime>%s</CreateTime><MsgType><![CDATA[text]]></MsgType><Content><![CDATA[%s]]></Content></xml>"; 
            $result = sprintf($this->itemTpl, $this->postArr['FromUserName'], $this->postArr['ToUserName'], $this->postArr['time'], "专属海报生成失败...");
            echo $result;
        }
        $this->itemTpl = "<xml><ToUserName><![CDATA[%s]]></ToUserName><FromUserName><![CDATA[%s]]></FromUserName><CreateTime>%s</CreateTime><MsgType><![CDATA[image]]></MsgType><Image><MediaId><![CDATA[%s]]></MediaId></Image></xml>";
        $msg = $media_id;
        $result = sprintf($this->itemTpl, $this->postArr['FromUserName'], $this->postArr['ToUserName'], $this->postArr['time'], $msg);
        echo $result;
        $wxapi->sendCustoMsg($this->postArr['FromUserName'],"text",array("content"=>"正在拼命生成您的专属推广海报..."));
        die;
    }
    private function getHb($content){
	    $info = $wxapi->sendRedPacket();
    }
    private function getOpenid(){
    	 $result = sprintf($this->itemTpl, $this->postArr['FromUserName'], $this->postArr['ToUserName'], $this->postArr['time'], $this->postArr['FromUserName']);
    	  echo $result;die;
    }
    /**
	* 普通消息回复
	* @access private
    * @param int  $this->app  应用(微信公众号),可根据不同应用处理不同业务
    * @param string $content  接收的消息内容
    */
    private function respoense($content){
    	$key_msg = array(
	        "hb"=>"红包"
	    );
	    $key_code = "other";
	    foreach ($key_msg as $key => $keyword) {
	        $pos = strpos($keyword, trim($content));
	        if ($pos !== false) {
	            $key_code = $key;
	            break;
	        }
	    }
	    switch ($key_code) {
	        case "hb":
	        	$msg = "红包说明";
	            break;
	        default:
                $msg = "系统升级中";
	            break;
	    }
	    $result = sprintf($this->itemTpl, $this->postArr['FromUserName'], $this->postArr['ToUserName'], $this->postArr['time'], $msg);
	    echo $result;die;
    }
}