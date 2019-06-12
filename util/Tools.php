<?php
/**
*工具类
*@author hedongji
*@since 2019-01-07 build
*/
namespace weapi\util;

class Tools {    
    /**
     * 接口成功回调统一处理
     * @access public
     * @param array     $data 回调数据
     * @param string        $msg 详细信息
     * @param code        0 
     * @return josn 
     */
	public static function httpok($data, $err_code = 0, $msg = '操作成功') {
        $return = [
            'err_code' => $err_code,
            'msg'  => $msg,
            'data' => $data
        ];
        echo json_encode($return,JSON_UNESCAPED_UNICODE);exit;
    }
    /**
     * 接口失败回调统一处理
     * @access public
     * @param array     $data 回调数据
     * @param string        $msg 详细信息
     * @param err_code       
     * @return josn 
     */
    public static function httpfalse($data = [],$err_code=40001, $msg="ERROR",$err_msg="ERROR") {
        $return = [
            'err_code' => $err_code,
            'err_msg' => $err_msg,
            'msg'  => $msg,
            'data' => $data
        ];
        echo json_encode($return,JSON_UNESCAPED_UNICODE);exit;
    }
    /**
     * 将xml转为array
     * @param string $xml
     * @throws WxPayException
     */
    public static function FromXml($xml)
    {   
        if(!$xml){
            return 50018;
        }
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);        
        return $data;
    }
    public static function arrayToXml($arr)
    {
        $xml = "<xml>";
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
        }
        $xml .= "</xml>";
        return $xml;
    }
    /**
     * 百迅请求订单http方法
     */
    public static function getJsonArray($url,$arr){
        if($url==""){
            return false;
        }
        $o=json_encode($arr,JSON_UNESCAPED_UNICODE);
        $this_header = array("content-type: application/x-www-form-urlencoded;charset=GBK");
        $ch = curl_init();
        curl_setopt($ch,CURLOPT_HTTPHEADER,$this_header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_URL,$url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $o);
        $result=curl_exec($ch);
         $result=iconv("GBK","UTF-8",$result);
        $backArr=json_decode($result,true);
        return $backArr;
    }
     /**
     * http curl post 
     */
    public static function curlByPost($url,$requestParams=array(),$requestHeader=array(),$timeOut=10){
        $header = array();
        $body = $requestParams;
        foreach ($requestHeader as $key => $val) {
            $header[] = "$key:$val";
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeOut);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        }
        
        curl_setopt($ch, CURLOPT_POST, 1); 
        if($body){
            curl_setopt($ch, CURLOPT_POSTFIELDS,  $body); 
        }
        if($header){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);  
        } 
         
        curl_setopt($ch, CURLOPT_HEADER, false);     
        if (stripos($url, "https://") !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSLVERSION, 1);
        }   
        $sContent = curl_exec($ch);
        $aStatus = curl_getinfo($ch);
        // print_r($sContent);die;
        curl_close($ch);
        if (intval($aStatus["http_code"]) == 200) {
            return $sContent;
        } else {
            return false;
        }
    }
    public static function curl_post($link,$data = [],$isJson = false){
    $curl = curl_init();
    if($isJson){
        $data = json_encode($data);
    }
    curl_setopt($curl, CURLOPT_URL, $link);
    curl_setopt($curl, CURLOPT_HEADER, 0);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    $res = curl_exec($curl);
    curl_close($curl);
    return $res;
}
    /**
     * http curl get
     */
    public static function curlGet($url, $timeOut = 10){
            $oCurl = curl_init();
            if (stripos($url, "https://") !== false) {
                curl_setopt($oCurl, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($oCurl, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($oCurl, CURLOPT_SSLVERSION, 1);
            }
            curl_setopt($oCurl, CURLOPT_URL, $url);
            curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($oCurl, CURLOPT_TIMEOUT, $timeOut);
            $sContent = curl_exec($oCurl);
            $aStatus = curl_getinfo($oCurl);
            curl_close($oCurl);
            if (intval($aStatus["http_code"]) == 200) {
                return $sContent;
            } else {
                return false;
            }
    }
    /**
     * post 微信api
     */
    public static function curlPostByWechat($url = '', $postData = '', $mch_config= array(),$options = array()){
        if (is_array($postData)) {
            $postData = http_build_query($postData);
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30); //设置cURL允许执行的最长秒数
        if (!empty($options)) {
            curl_setopt_array($ch, $options);
        }
        //https请求 不验证证书和host
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        if ($mch_config) {
            //第一种方法，cert 与 key 分别属于两个.pem文件
            //默认格式为PEM，可以注释
            curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
            curl_setopt($ch,CURLOPT_SSLCERT,$mch_config['sslcert_path']);
            //默认格式为PEM，可以注释
            curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
            curl_setopt($ch,CURLOPT_SSLKEY,$mch_config['sslckey_path']);
            //第二种方式，两个文件合成一个.pem文件
            //curl_setopt($ch,CURLOPT_SSLCERT,getcwd().'/all.pem');
        }
        $data = curl_exec($ch);
        curl_close($ch);
        return $data;
    }
    /**
     * 去除特殊字符
     */
    public static function removeEmoji($text) {
            $clean_text = "";
            // Match Emoticons
            $regexEmoticons = '/[\x{1F600}-\x{1F64F}]/u';
            $clean_text = preg_replace($regexEmoticons, '', $text);
            // Match Miscellaneous Symbols and Pictographs
            $regexSymbols = '/[\x{1F300}-\x{1F5FF}]/u';
            $clean_text = preg_replace($regexSymbols, '', $clean_text);
            // Match Transport And Map Symbols
            $regexTransport = '/[\x{1F680}-\x{1F6FF}]/u';
            $clean_text = preg_replace($regexTransport, '', $clean_text);
            // Match Miscellaneous Symbols
            $regexMisc = '/[\x{2600}-\x{26FF}]/u';
            $clean_text = preg_replace($regexMisc, '', $clean_text);
            // Match Dingbats
            $regexDingbats = '/[\x{2700}-\x{27BF}]/u';
            $clean_text = preg_replace($regexDingbats, '', $clean_text);

            preg_match_all('/[a-zA-Z0-9\x{4e00}-\x{9fff}]+/u', $clean_text, $matches);
            $clean_text = join('', $matches[0]);
            return $clean_text;
    }
    /**
     * 随机字符串
     */
    public static function createNonceStr($length = 16) {
          $chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789";
          $str = "";
          for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
          }
          return $str;
    }
    /**
     * 获取客户端IP地址
     * @return string
     */
    public static function get_client_ip() {
        if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } elseif(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $ip = getenv('REMOTE_ADDR');
        } elseif(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $ip = $_SERVER['REMOTE_ADDR'];
        } else {
            $ip = '0.0.0.0';
        }
        return preg_match('/[\d\.]{7,15}/', $ip, $matches) ? $matches[0] : '';
    }

}