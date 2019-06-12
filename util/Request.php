<?php
/**
*请求类
*@author hedongji
*@since 20180516 build
*/
namespace weapi\util;
use weapi\util\Tools;
class Request {
	/**
     * @var array 请求参数
     */
    public $param   = [];
    public $get     = [];
    public $post    = [];
    public $header  = [];


	/**
     * 构造函数
     * @access public
     * @param array $options 参数
     */
    public function __construct($options = [])
    {
        $content = file_get_contents('php://input');
        $xml_parser = xml_parser_create();
        if(!xml_parse($xml_parser,$content,true)){
            xml_parser_free($xml_parser);
            $this->post = (array) json_decode($content, true);
        }else{
            $this->post = Tools::FromXml($content);   
        }
        
        if(empty($this->post)){
           $this->post =  $_POST;
        }
        $this->get = $_GET;
        $this->param = array_merge($this->get, $this->post);
        
        $this->header = $this->header();
    }
    
    /**
     * 设置或者获取当前的Header
     * @access public
     * @param string|array  $name header名称
     * @param string        $default 默认值
     * @return string
     */
    public function header($name = '', $default = null)
    {
        if (empty($this->header)) {
            $header = [];
            if (function_exists('apache_request_headers') && $result = apache_request_headers()) {
                $header = $result;
            } else {
                $server = $_SERVER;
                foreach ($server as $key => $val) {
                    if (0 === strpos($key, 'HTTP_')) {
                        $key          = str_replace('_', '-', strtolower(substr($key, 5)));
                        $header[$key] = $val;
                    }
                }
                if (isset($server['CONTENT_TYPE'])) {
                    $header['content-type'] = $server['CONTENT_TYPE'];
                }
                if (isset($server['CONTENT_LENGTH'])) {
                    $header['content-length'] = $server['CONTENT_LENGTH'];
                }
            }
            $this->header = array_change_key_case($header);
        }
        if (is_array($name)) {
            return $this->header = array_merge($this->header, $name);
        }
        if ('' === $name) {
            return $this->header;
        }
        $name = str_replace('_', '-', strtolower($name));
        return isset($this->header[$name]) ? $this->header[$name] : $default;
    }
    public function get($name){
        if (empty($name)) {
            return $this->get;
        }
        // print_r($this->get);die;
        $get_key = array_keys($this->get);
        if (in_array($name, $get_key)) {
            return $this->get[$name];
        }
        return [];
    }

    public function post($name){
        if (empty($name)) {
            return $this->post;
        }
        $get_key = array_keys($this->post);
        if (in_array($name, $get_key)) {
            return $this->post[$name];
        }
        return [];
    }

    public function param($name){
        if (empty($name)) {
            return $this->param;
        }
        $get_key = array_keys($this->param);
        if (in_array($name, $get_key)) {
            return $this->param[$name];
        }
        return [];
    }
     
    public function getRequest($url){
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        if (stripos($url, "https://") !== false) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSLVERSION, 1);
        }   
        curl_setopt($curl, CURLOPT_URL, $url);
        $res = curl_exec($curl);
        curl_close($curl);
        return $res;    
    }

    public function postRequest($url,$body,$header=[]){
        $body = json_encode($body,JSON_UNESCAPED_UNICODE);
        $body = iconv("UTF-8","GBK",$body);
        $curl = curl_init ();
        curl_setopt ( $curl, CURLOPT_URL, $url );
        curl_setopt ( $curl, CURLOPT_POST, 1 );
        curl_setopt ( $curl, CURLOPT_RETURNTRANSFER, 1 );
        curl_setopt($curl, CURLOPT_TIMEOUT, 10); 
        if ($header) {
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);  
        }
        if (stripos($url, "https://") !== false) {
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($curl, CURLOPT_SSLVERSION, 1);
        }  
        curl_setopt ( $curl, CURLOPT_POSTFIELDS, $body );
        $return = curl_exec ( $curl );
        curl_close ( $curl );
        return $return;
    }
}