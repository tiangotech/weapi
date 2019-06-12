<?php
/**
*微信常用的api
*@author hedongji
*@since 2019-01-07 build
*/
namespace weapi\model;

defined('IN_API') or exit('Access Denied');
use weapi\model\WechatPay;
use weapi\config\WechatApiConfig;
use weapi\config\BaisonConfig;
use weapi\config\WechatPayConfig;
use weapi\util\Tools;
use weapi\util\Log;
use weapi\util\DB;
class WechatApi {
	  public $log;
	  public $app;

    //*所有方法都需要 $app (应用参数)
    //需要auth验证(必须$uid)  __construct($app,$auth)
    public function __construct($app){
    	  if (!in_array($app, WechatApiConfig::PLATFORM)) {
  			    return 40011;
  		  }

        $this->app = $app;

        $appconfig = WechatApiConfig::getAppConfig($app);
        if ($appconfig === false) {
            return 40011;
        }
        $this->appID = $appconfig["APPID"];
        $this->secret = $appconfig["APPSECRET"];
    }
    /**
     * 企业付款
     * @param string $app
     * @param string $openid 
     * @param string $amount  金额
     * @param string $tradeno   流水号
     * @param string  $mch  商户
     * @param string  $desc  
     */
    public function transfers($app,$openid,$tradeno,$amount,$mch="",$desc="微信红包"){
        if (!is_numeric($amount)) {
            return 50014;
        }
        if (empty($mch)) {
            $mch = WechatPayConfig::sendhbAppUnionMch($app);
        }
        if (!in_array($mch, WechatPayConfig::MTC)) return 50014;
        $config = WechatPayConfig::$$mch;
        if (empty($config)) {
            return 50014;
        }
		    $unified = array(
		        'mch_appid' => $this->appID,
		        'mchid' => $config['mchid'],
		        'nonce_str' => Tools::createNonceStr(),
		        'partner_trade_no' =>  $tradeno,
		        'openid' => $openid,
		        'check_name' => 'NO_CHECK',
		        'amount' => intval($amount * 100),     
		        'desc'=>$desc,   
		        'spbill_create_ip'=>$_SERVER['REMOTE_ADDR'],           
		    );
		    $unified['sign'] = WechatPay::MakeSign($unified,$config);
		    $unified = json_decode(json_encode($unified),true);
		    $responseXml = Tools::curlPostByWechat('https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers',Tools::arrayToXml($unified),$config);
		    $unifiedOrder = simplexml_load_string($responseXml, 'SimpleXMLElement', LIBXML_NOCDATA);
		    if ($unifiedOrder === false) {
		        $this->db->rollBack();
		        return 50021;
		    }
		    $this->log = new Log("transfers",30);
		    if ($unifiedOrder->result_code != 'SUCCESS') {
		        $this->log->notice(" 流水号".$tradeno."，红包金额".$amount."，失败原因：[".$unifiedOrder->err_code."]".$unifiedOrder->err_code_des);
		        return array(
                "app" => $app,
                "openid" => $openid,
                "tradeno" => $tradeno,
                "amount" => $amount,
                "result_code" => $unifiedOrder->result_code,
                "err_code" => $unifiedOrder->err_code,
                "err_code_des" => $unifiedOrder->err_code_des
            );
		    }
		    $this->log->notice("用户".$uid."，红包金额".$amount);
        return array(
            "app" => $app,
            "openid" => $openid,
            "tradeno" => $tradeno,
            "amount" => $amount,
            "result_code" => $unifiedOrder->result_code,
            "err_code" => 0,
            "err_code_des" => ""
        );	
    }
    /**
     * 现金红包
     * @param string $openid
     * @param string $amount 红包金额
     * @param string $totalFee 红包金额
     * @param string $tradeno   红包单号
     * @param string  $sendName  发送方
     * @param string  $wishing  红包祝福语
     * @param string  $actName  活动名称
     * @param string  $mch  商户
     * @param string  $scene_id  场景
     */
    public function sendRedPacket($openid, $amount, $tradeno, $sendName,$wishing,$actName,$mch="",$scene_id='PRODUCT_2'){
        if (!is_numeric($amount)) {
              return 50014;
          }
        if (empty($mch)) {
            $mch = WechatPayConfig::sendhbAppUnionMch($this->app);
        }
    	  $config = WechatPayConfig::$$mch;
        $unified = array(
            'wxappid' => $this->appID,
            'send_name' => $sendName,
            'mch_id' => $config['mchid'],
            'nonce_str' => Tools::createNonceStr(),
            're_openid' => $openid,
            'mch_billno' => $tradeno,
            'client_ip' => '127.0.0.1',
            'total_amount' => intval($amount * 100),       //单位 转为分
            'total_num'=>1,     //红包发放总人数
            'wishing'=>$wishing,      //红包祝福语
            'act_name'=>$actName,           //活动名称
            'remark'=>'remark',//备注信息，如为中文注意转为UTF8编码
            'scene_id'=>$scene_id,      //发放红包使用场景，红包金额大于200时必传
        );
        $unified['sign'] = WechatPay::MakeSign($unified,$config);
        $responseXml = Tools::curlPostByWechat('https://api.mch.weixin.qq.com/mmpaymkttransfers/sendredpack',Tools::arrayToXml($unified),$config);
        $unifiedOrder = simplexml_load_string($responseXml, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($unifiedOrder === false) {
            return false;
        }
        $this->log = new Log("sendRedPacket",30);
        if ($unifiedOrder->result_code != 'SUCCESS') {
            $this->log->notice("流水号".$tradeno."，红包金额".$amount."，失败原因：[".$unifiedOrder->err_code."]".$unifiedOrder->err_code_des);
            return array(
                "app" => $app,
                "openid" => $openid,
                "tradeno" => $tradeno,
                "amount" => $amount,
                "result_code" => $unifiedOrder->result_code,
                "err_code" => $unifiedOrder->err_code,
                "err_code_des" => $unifiedOrder->err_code_des
            );
        }
        $this->log->notice("流水号".$uid."，红包金额".$amount]);
        return array(
            "app" => $app,
            "openid" => $openid,
            "tradeno" => $tradeno,
            "amount" => $amount,
            "result_code" => $unifiedOrder->result_code,
            "err_code" => 0,
            "err_code_des" => ""
        );  
    }

    //自定义二维码推广海报
    public function buildDiyPoster($openid,$posterpath,$posterurl,$haveheadimg=0){
        include_once __DIR__.'/../vendor/phpQrcode/phpqrcode.php';

        $dir = __DIR__.'/../log/QRimg/';
        if (!file_exists($dir)){
            mkdir($dir,0777,true);
        }
        \QRcode::png($posterurl, $dir.$openid.".png",QR_ECLEVEL_L,12);

        $thumb = imagecreatetruecolor(550, 550);//创建一个300x300图片，返回生成的资源句柄
        $source = imagecreatefromstring(file_get_contents($dir.$openid.".png"));
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, 260, 260, 480, 480);
        $dst_qr = @imagecreatefromstring(file_get_contents($posterpath));
        imagecopy($dst_qr, $thumb, 250, 380, 0, 0, 240, 240);//加水印
        imagedestroy($thumb);//销毁
        ob_start();//启用输出缓存，暂时将要输出的内容缓存起来
        imagejpeg($dst_qr,  NULL, 100);//输出
        $poster = ob_get_contents();//获取刚才获取的缓存
        ob_end_clean();//清空缓存
        imagedestroy($dst_qr);
        if($haveheadimg){
            $user_info = $this->getUserinfo($openid);
            $thumb_headimg = imagecreatetruecolor(150, 150);
            $ch = curl_init($user_info['headimgurl']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $headimg_source = curl_exec($ch);
            curl_close($ch);

            $source = @imagecreatefromstring($headimg_source);
            imagecopyresampled($thumb_headimg, $source, 0, 0, 0, 0, 40, 40, 150, 150);
            $dst_icon = imagecreatefromstring($poster);
            imagecopy($dst_icon, $thumb_headimg, 350, 480, 0, 0, 40, 40);
            imagedestroy($thumb_headimg);
        }else{
            $dst_icon = imagecreatefromstring($poster);
        }
        ob_start();
        $tmp_path = __DIR__."/../image/$openid.jpg";
        imagejpeg($dst_icon, $tmp_path);
        ob_end_clean();
        imagedestroy($dst_icon);
        //将替换好的海报，新增到临时素材
        $post_data['media'] = "@".$tmp_path;
        $url = "http://api.weixin.qq.com/cgi-bin/media/upload?access_token=".$this->getAccessToken($this->app)."&type=image";
        $result = json_decode($this->curl_http_media($url, "post",$post_data));
        if (file_exists($tmp_path)) {
            unlink($tmp_path);
        }
        if (file_exists(__DIR__.'/log/QRimg/'.$openid.".png")) {
            unlink(__DIR__.'/log/QRimg/'.$openid.".png")
        }
        if(isset($result->media_id)) {
            return $result->media_id;
        }else{
            return false;
        }
    }
    //推广海报
    public function buildPoster($openid,$ticket=""){
        if (empty($ticket)) {
            $url = "https://api.weixin.qq.com/cgi-bin/qrcode/create?access_token=".$this->getAccessToken($this->app);//获取永久ticket
            $responjson = json_encode(array("action_name"=>"QR_LIMIT_STR_SCENE",'action_info' => ['scene' => ['scene_str' => 'invite_'.$openid]]));
            $rel = Tools::curlPostByWechat($url,$responjson);
            $result = json_decode($rel,true);
            $ticket = $result['ticket'];
        }

        $url = 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . urlencode($ticket);
        $ch = curl_init ();
        curl_setopt ($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt ($ch, CURLOPT_URL, $url);
        ob_start ();
        curl_exec ($ch);
        $qr_content = ob_get_contents();
        ob_end_clean ();
        $thumb = imagecreatetruecolor(200, 200);//创建一个300x300图片，返回生成的资源句柄
        $source = imagecreatefromstring($qr_content);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, 200, 200, 430, 430);
        $poster_path =  __DIR__.'/../poster.jpg';
        $dst_qr = @imagecreatefromstring(file_get_contents($poster_path));
        imagecopy($dst_qr, $thumb, 220, 500, 0, 0, 200, 200);
        imagedestroy($thumb);
        ob_start();//启用输出缓存，暂时将要输出的内容缓存起来
        imagejpeg($dst_qr,  NULL, 100);//输出
        $poster = ob_get_contents();//获取刚才获取的缓存
        ob_end_clean();//清空缓存
        imagedestroy($dst_qr);
        //获取头像,直接访问微信的获取用户接口，具体代码代码省略
        $user_info = $this->getUserinfo($openid);
        $tmp_path = __DIR__."/../image/$openid.jpg";
        $thumb_headimg = imagecreatetruecolor(150, 150);
        $ch = curl_init($user_info['headimgurl']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $headimg_source = curl_exec($ch);
        curl_close($ch);
        $source = @imagecreatefromstring($headimg_source);
        imagecopyresampled($thumb_headimg, $source, 0, 0, 0, 0, 40, 40, 150, 150);
        $dst_icon = imagecreatefromstring($poster);
        imagecopy($dst_icon, $thumb_headimg, 300, 580, 0, 0, 40, 40);
        imagedestroy($thumb_headimg);
        ob_start();
        imagejpeg($dst_icon, $tmp_path);
        ob_end_clean();
        imagedestroy($dst_icon);
        $post_data['media'] = "@".$tmp_path;
        $url = "http://api.weixin.qq.com/cgi-bin/media/upload?access_token=".$this->getAccessToken($this->app)."&type=image";
        $result = json_decode($this->curl_http_media($url, "post",$post_data));
        if(isset($result->media_id)) {
            unlink($tmp_path);
            return $result->media_id;
        }else{
            return false;
        }
    }
    //获取用户信息
    public function getUserinfo($openid){
        $data = Tools::curlGet("https://api.weixin.qq.com/cgi-bin/user/info?openid=".$openid."&access_token=".$this->getAccessToken($this->app)."&lang=zh_CN");
        $data = json_decode($data,true);
        return $data;
    }
    /**
     * 获取openid
     */
  	public function getOpenid(){
  		  if (!isset($_GET['code'])){
            if (isset($_SERVER['HTTPS'])) {
                $scheme = $_SERVER['HTTPS']=='on' ? 'https://' : 'http://';
            }else{
                $scheme = 'http://';
            }
            $baseUrl = urlencode($scheme.$_SERVER['HTTP_HOST'].$_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
            $url = $this->__CreateOauthUrlForCode($baseUrl);
            Header("Location: $url");
            exit();
        } else {
              //获取code码，以获取openid
          $code = $_GET['code'];
          $openid = $this->getOpenidFromMp($code);
          return ["openid"=>$openid];
        }
  	}
	 //获取微信sdk签名， 微信分享调用
    public function getSignPackage($url) { 
        $url= urlencode($url);
  	    $jsapiTicket = $this->getJsApiTicket();
  	    $timestamp = time();
  	    $nonceStr = Tools::createNonceStr();
  	    $string = "jsapi_ticket=$jsapiTicket&noncestr=$nonceStr&timestamp=$timestamp&url=$url";
  	    $signature = sha1($string);
  	    $signPackage = array(
  	        "appId"     => $this->appID,
  	        "nonceStr"  => $nonceStr,
  	        "timestamp" => $timestamp,
  	        "url"       =>$url,
  	        "signature" => $signature,
  	        "rawString" => $string,
  	        "jsapiTicket" => $jsapiTicket
  	    );
  	    return $signPackage;
    }
    /**
     * 发 客服 消息
     * @param string $openid 
     * @param string $type 默认video
     * @param array $data  
     	$type= image/voice/music/mpnews  array("media_id"=>$media_id)
     	$type= text  array("content"=>$content)
     	$type= wxcard  array("card_id"=>"123dsdajkasd231jhksad" )
        $type=miniprogrampage array("title"=>"四人斗地主!","appid"=>"wx25ce4089caf8e9ea","pagepath"=>"pages/index/index","thumb_media_id"=>$media_id) 
        $type=video array("media_id"=>$media_id,"thumb_media_id"=>$thumb_media_id,"title"=>"客服操作流程视频","description"=>"")时必须
     	$type=news  array("articles"=>["picurl"=>"PIC_URL","url"=>"URL","title"=>"Happy Day","description"=>"Is Really A Happy Day"])时必须
     */
    public function sendCustoMsg($openid,$type,$data=array()){
      	$url = "https://api.weixin.qq.com/cgi-bin/message/custom/send?access_token=".$this->getAccessToken($this->app);
      	switch ($type) {
        		case 'video':
          			$responseText = array("touser"=>$openid,"msgtype"=>"video","video"=>$data);
          			break;
        		case 'image':
          			$responseText = array("touser"=>$openid,"msgtype"=>"image","image"=>$data);
          			break;
        		case 'miniprogrampage':
          			$responseText = array("touser"=>$openid,"msgtype"=>"miniprogrampage","miniprogrampage"=>$data);
          			break;
        		case 'text':
        			//文本内容<a href="http://www.qq.com" data-miniprogram-appid="appid" data-miniprogram-path="pages/index/index">点击跳小程序</a>
        			//说明：
    				//1.data-miniprogram-appid 项，填写小程序appid，则表示该链接跳小程序；
    				//2.data-miniprogram-path项，填写小程序路径，路径与app.json中保持一致，可带参数；
    				//3.对于不支持data-miniprogram-appid 项的客户端版本，如果有herf项，则仍然保持跳href中的网页链接；
    				//4.data-miniprogram-appid对应的小程序必须与公众号有绑定关系。
        				$responseText = array("touser"=>$openid,"msgtype"=>"text","text"=>$data);
            			break;
        		case 'voice':
          			$responseText = array("touser"=>$openid,"msgtype"=>"voice","voice"=>$data);
          			break;
        		case 'music':
          			$responseText = array("touser"=>$openid,"msgtype"=>"music","music"=>$data);
          			break;
        		case 'news':
          			$responseText = array("touser"=>$openid,"msgtype"=>"news","news"=>$data);
          			break;
        		case 'mpnews':
          			$responseText = array("touser"=>$openid,"msgtype"=>"mpnews","mpnews"=>$data);
          			break;
        		case 'wxcard':
          			$responseText = array("touser"=>$openid,"msgtype"=>"wxcard","wxcard"=>$data);
          			break;
      	}
      	$responjson = json_encode($responseText,JSON_UNESCAPED_UNICODE);
      	$rel = Tools::curlPostByWechat($url,$responjson);
      	$rel = json_decode($rel,true);
      	if ($rel['errcode'] != 0) {
      		  return false;
      	}
    }
    //获取微信素材详细信息
    public function getMaterialByMediaid($media_id){
      	$url = "https://api.weixin.qq.com/cgi-bin/material/get_material?access_token=".$this->getAccessToken($this->app);
      	$responjson = json_encode(array("media_id"=>$media_id));
      	$rel = Tools::curlPostByWechat($url,$responjson);
      	$arr = json_decode($rel,true);
      	return $arr;
    }
    //获取微信素材图片（image）、视频（video）、语音 （voice）、图文（news）
    public function getMaterialList($type="image",$offset=0,$count=100){
      	$url = "https://api.weixin.qq.com/cgi-bin/material/batchget_material?access_token=".$this->getAccessToken($this->app);
      	$responjson = json_encode(array("type"=>"image","offset"=>$offset,"count"=>$count));
      	$rel = Tools::curlPostByWechat($url,$responjson);
      	$arr = json_decode($rel,true);
      	return $arr;
    }
    //jsapi票据生成
    //负载均衡服务器，memcache实现中心化
    private function getJsApiTicket() {
      // jsapi_ticket 应该全局存储与更新
      	$json_file = __DIR__.'/../accessToken/'.$this->app."_jsapi_ticket.json";
      	$data =  '{"expire_time":0}';
      	if (file_exists($json_file)) {
      		  $data = json_decode(file_get_contents($json_file));
      	}else{
      		  $data = json_decode($data);
      	}
      // $memdb = new \Memcache;
      // $memdb->connect(BaisonConfig::MEMCACHE_HOST,BaisonConfig::MEMCACHE_POST);
      // $data = $memdb->get($this->app."_jsapi_ticket");
      // if (empty($data)) {
      //     $data = '{"expire_time":0}';
      // }
      // $data = json_decode($data);
      	if ($data->expire_time < time()) {
          	$accessToken = $this->getAccessToken();
          	$url = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?type=jsapi&access_token=$accessToken";
          	$res = json_decode(Tools::curlGet($url));
          	$ticket = $res->ticket;
  	        if ($ticket) {
  	          	$data->expire_time = time() + 7000;
  	          	$data->jsapi_ticket = $ticket;
  	          	$fp = fopen($json_file, "w");
  	          	fwrite($fp, json_encode($data));
  	          	fclose($fp);

                // $data = $memdb->set($this->app."_jsapi_ticket",json_encode($data));
  	        }
      	}else{
        	  $ticket = $data->jsapi_ticket;
      	}
      return $ticket;
    }
    //获取微信jsapi token
    //负载均衡服务器，memcache实现中心化
    public function getAccessToken() {
      // $memdb = new \Memcache;
      // $memdb->connect(BaisonConfig::MEMCACHE_HOST,BaisonConfig::MEMCACHE_POST);
      // $data = $memdb->get($this->app."_access_token");
      // if (empty($data)) {
      //     $data = '{"expire_time":0}';
      // }
      // $data = json_decode($data);

      	$token_file = __DIR__.'/../accessToken/'.$this->app."_access_token.json";
      	$data = '{"expire_time":0}';
      	if (file_exists($token_file)) {
      		  $data = json_decode(file_get_contents($token_file));
      	}else{
      		  $data = json_decode($data);
      	}
        if ($data->expire_time < time()) {
            $url = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=$this->appID&secret=$this->secret";
  	        $res = json_decode(Tools::curlGet($url));
            if (!isset($res->access_token)) {
                $this->log->critical($res);
            }
  	        $access_token = $res->access_token;
  	        if ($access_token) {
  		          $data->expire_time = time() + 7000;
  		          $data->access_token = $access_token;

                  // $data = $memdb->set($this->app."_access_token",json_encode($data));

        		    $fp = fopen($token_file, "w");
        		    fwrite($fp, json_encode($data));
        		    fclose($fp);
  	       }
        }else{
          	$access_token = $data->access_token;
        }
        return $access_token;
    }
	   /**
     * 构造获取code的url连接
     * @param string $redirectUrl 微信服务器回跳的url，需要url编码
     * @return 返回构造好的url
     */
    private function __CreateOauthUrlForCode($redirectUrl){
        $urlObj["appid"] = $this->appID;
        $urlObj["redirect_uri"] = "$redirectUrl";
        $urlObj["response_type"] = "code";
        $urlObj["scope"] = "snsapi_base";
        $urlObj["state"] = "STATE"."#wechat_redirect";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://open.weixin.qq.com/connect/oauth2/authorize?".$bizString;
    }
    /**
     * 通过code从工作平台获取openid机器access_token
     * @param string $code 微信跳转回来带上的code
     * @return openid
     */
    public function GetOpenidFromMp($code){
        $url = $this->__CreateOauthUrlForOpenid($code);
        $res = Tools::curlGet($url);
        //取出openid
        $data = json_decode($res,true);
        $this->UserInfo = $data;
        $openid = $data['openid'];
        return $openid;
    }
    /**
     * 构造获取open和access_toke的url地址
     * @param string $code，微信跳转带回的code
     * @return 请求的url
     */
    private function __CreateOauthUrlForOpenid($code){
        $urlObj["appid"] = $this->appID;
        $urlObj["secret"] = $this->secret;
        $urlObj["code"] = $code;
        $urlObj["grant_type"] = "authorization_code";
        $bizString = $this->ToUrlParams($urlObj);
        return "https://api.weixin.qq.com/sns/oauth2/access_token?".$bizString;
    }
    /**
     * 拼接签名字符串
     * @param array $urlObj
     * @return 返回已经拼接好的字符串
     */
    private function ToUrlParams($urlObj){
        $buff = "";
        foreach ($urlObj as $k => $v)
        {
            if($k != "sign") $buff .= $k . "=" . $v . "&";
        }
        $buff = trim($buff, "&");
        return $buff;
    }

    /**
    * http请求方式: 默认GET
    */
    private function curl_http_media($url, $method="GET", $postfields){
        $ch=curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        if (!empty($postfields)){
          $hadFile=false;
        if (is_array($postfields) && isset($postfields['media'])) {
                /* 支持文件上传 */
            if (class_exists('\CURLFile')){
                curl_setopt($ch, CURLOPT_SAFE_UPLOAD, true);
                foreach ($postfields as $key => $value) {
                    if ($this->isPostHasFile($value)) {
                        $postfields[$key] = new \CURLFile(realpath(ltrim($value, '@')));
                        $hadFile = true;
                    }
                }
            }elseif (defined('CURLOPT_SAFE_UPLOAD')) {
                if ($this->isPostHasFile($value)) {
                    curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
                    $hadFile = true;
                }
            }
        }
        $tmpdatastr = (!$hadFile && is_array($postfields)) ? http_build_query($postfields) : $postfields;
            curl_setopt($ch, CURLOPT_POSTFIELDS, $tmpdatastr);
        }
        $ssl=preg_match('/^https:\/\//i',$url) ? TRUE : FALSE;
        curl_setopt($ch, CURLOPT_URL, $url);
        if($ssl){
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE); // 不从证书中检查SSL加密算法是否存在
        }
        $response=curl_exec($ch);
        curl_close($ch);
        if(empty($response)){
            exit("错误请求");
        }
        return $response;
    }
    private function isPostHasFile($value){
      if (is_string($value) && strpos($value, '@') === 0 && is_file(realpath(ltrim($value, '@')))) {
          return true;
      }
      return false;
    }
}