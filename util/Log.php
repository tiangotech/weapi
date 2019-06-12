<?php

/**
*Log类
*@author hedongji
*@since 2019-01-13 build
*/
/**
	* 日志 错误信息大于WARNING 级别发送邮件提醒
	* @access public
    * @param string  $channel  通道（名称）
    * @param string  $message  信息内容
    * @param int  	 $level    日志级别 DEBUG（100）：详细的调试信息。
    *									INFO（200）：有兴趣的事件
    *									NOTICE （250）：正常但重要的事件。
	*									WARNING （300）：非错误的异常情况。
	*									ERROR （400）：运行错误，不需要立即处理，但需要记录和监视。
	*									CRITICAL （500）：危急情况。
	*									ALERT （550）：必须立即处理。
	*									EMERGENCY （600）：紧急情况：系统无法使用。
	* @param array 	 $extra  	额外参数
	*/
namespace weapi\util;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\DeduplicationHandler;
use Monolog\Handler\SmtpMailerHandler;
use Monolog\Handler\BufferHandler;
use Monolog\Formatter\HtmlFormatter;
use Monolog\Processor\WebProcessor;
use Monolog\Processor\IntrospectionProcessor;
use Monolog\Processor\MemoryUsageProcessor;
use Monolog\Processor\MemoryPeakUsageProcessor;
class Log {
	protected $logHeader;
	protected $buffer;
	protected $emailbuffer;
	protected $bufferLimit = 10;
	protected $bufferLevel = 'DEBUG';
	protected $maxFiles = 30;
	protected $emailTo = ["1406835034@qq.com"];
	protected $emailLevel = 'CRITICAL';
	/**
	* 初始化momolog
	* @access public
    * @param string  $channel   通道（名称）项目会根据该名称构建文件夹
    * @param string  $maxFiles  保存的文件数
    * @param array  $emailTo  	收件人
    */
	public function __construct($channel,$maxFiles,$emailTo=""){
		if ($emailTo) {
			$this->emailTo = $emailTo;
		}
        $this->logHeader = new Logger($channel);
        $this->maxFiles = $maxFiles;
        $rotatingFileHandler = new RotatingFileHandler(__DIR__."/../log/".$channel.'/'.$channel.'.log', $maxFiles);
  		$output = "%datetime% : %channel%.%level_name% > %message% %context% %extra%\r\n";
  		$dateFormat = "[Y-m-d H:i:s]";
  		$formatter = new LineFormatter($output, $dateFormat);
        $rotatingFileHandler->setFormatter($formatter);
        $this->buffer = new BufferHandler($rotatingFileHandler,$this->bufferLimit,Logger::DEBUG,true,true);
        $this->logHeader->pushHandler($this->buffer);

        //errorr日志邮件提醒
        $smtpMailerHandler = new SmtpMailerHandler($this->emailTo, "$channel", "Log@log.com",Logger::ERROR);
  		$htmlFormatter = new HtmlFormatter();
        $smtpMailerHandler->setFormatter($htmlFormatter);
        $smtpMailerHandler->pushProcessor(new WebProcessor());//将当前请求URI，请求方法和客户端IP添加到日志记录中
        $smtpMailerHandler->pushProcessor(new MemoryUsageProcessor());//将当前内存使用量添加到日志记录中
        $smtpMailerHandler->pushProcessor(new MemoryPeakUsageProcessor());//将峰值内存使用量添加到日志记录中
        // $smtpMailerHandler->pushProcessor(new IntrospectionProcessor());//添加发起日志调用的行/文件/类/方法

        $this->emailbuffer = new BufferHandler($smtpMailerHandler,$this->bufferLimit,Logger::ERROR,true,true);
  		// $buffer = $buffer?$buffer:new DeduplicationHandler($smtpMailerHandler,null,Logger::ERROR,60);
  		$this->logHeader->pushHandler($this->emailbuffer);
    }
    /**
	* register_shutdown_function处理函数
	* @access public
    */
    public function my_error_catch(){
        if ($error = error_get_last()) {
        	//============发送系统报错邮件间隔====================
        	$file = __DIR__."/../runerr/my_error_catch.json";
	    	$data = '{"expire_time":0}';
	    	if (file_exists($file)) {
	    		$data = json_decode(file_get_contents($file));
	    	}else{
	    		$data = json_decode($data);
	    	}
	    	//===================================================
            $log = new Log("RUN",30);
            //由于由于未记录的MIME类型会默认使用$HTTP_RAW_POST_DATA自动填充，但其已被摒弃，会出现warning,可以在php.ini设置always_populate_raw_post_data’=‘-1’
            if (strpos($error['message'],'HTTP_RAW_POST_DATA') === false) {
            	$message = "my_error_catch:".$error['type'] . ' Msg: ' . $error['message'] . ' in ' . $error['file'] . ' on line ' . $error['line'];
	            //============一分钟一次报错邮件=============
	            if ($data->expire_time>time()) {
	    			$log->notice($message);
	    		}else{
	    			$log->critical($message);
	    			$data->expire_time = time()+60;
	    			$fp = fopen($file, "w");
      		        fwrite($fp, json_encode($data));
      		        fclose($fp);
	    		}
	    		//===========================================
	            echo '{"code":-1,"msg":"系统维护中！！！详细情况请找客服了解！","data":[]}';
	            $log->bufferClose(); exit; 
            }
        }
        
    }
    /**
	* exception错误捕获处理函数
	* @access public
    */
    public function my_exception_handler($exception) {
    	//============发送系统报错邮件间隔====================
        	$file = __DIR__."/../runerr/my_exception_handler.json";
	    	$data = '{"expire_time":0}';
	    	if (file_exists($file)) {
	    		$data = json_decode(file_get_contents($file));
	    	}else{
	    		$data = json_decode($data);
	    	}
	    //===================================================
    	$log = new Log("RUN",30);
    	$message = "my_exception_handler:".$exception->getMessage();
    	print_r($exception->getTrace());die;
        //============一分钟一次报错邮件=============
	            if ($data->expire_time>time()) {
	    			$log->notice($message);
	    		}else{
	    			$log->critical($message);
	    			$data->expire_time = time()+60;
	    			$fp = fopen($file, "w");
      		        fwrite($fp, json_encode($data));
      		        fclose($fp);
	    		}
	   	//===========================================
        $log->bufferClose();
        echo '{"code":-1,"msg":"系统维护中！！！详细情况请找客服了解！","data":[]}';
        exit;
    }
    /**
	* error错误捕获处理函数
	* @access public
    */
    public function my_error_handler($errno, $errstr, $errfile, $errline) {
    	//============发送系统报错邮件间隔====================
        	$file = __DIR__."/../runerr/my_error_handler.json";
	    	$data = '{"expire_time":0}';
	    	if (file_exists($file)) {
	    		$data = json_decode(file_get_contents($file));
	    	}else{
	    		$data = json_decode($data);
	    	}
	    //===================================================
    	// if ($errno<=2) {
	    	$log = new Log("RUN",30);
	    	$message ="my_error_handler:". $errno . ' Msg: ' . $errstr . ' in ' . $errfile . ' on line ' . $errline;
	        //============一分钟一次报错邮件=============
	            if ($data->expire_time>time()) {
	    			$log->notice($message);
	    		}else{
	    			$log->critical($message);
	    			$data->expire_time = time()+60;
	    			$fp = fopen($file, "w");
      		        fwrite($fp, json_encode($data));
      		        fclose($fp);
	    		}
	   		//===========================================
	        $log->bufferClose();
	        echo '{"code":-1,"msg":"系统维护中！！！详细情况请找客服了解！","data":[]}';
	        exit;
	    // }
    }
    //DEBUG（100）：详细的调试信息。
	public function debug($message,$context=[]){
		$this->logHeader->debug($message,$context);
	}
	//INFO（200）：有兴趣的事件
	public function info($message,$context=[]){
		$this->logHeader->info($message,$context);
	}
	//NOTICE （250）：正常但重要的事件。
	public function notice($message,$context=[]){
		$this->logHeader->notice($message,$context);
	}
	//WARNING （300）：非错误的异常情况。
	public function warning($message,$context=[]){
		$this->logHeader->warning($message,$context);
	}
	//ERROR （400）：运行错误，不需要立即处理，但需要记录和监视。
	public function error($message,$context=[]){
		$this->logHeader->error($message,$context);
	}
	//CRITICAL （500）：危急情况。
	public function critical($message,$context=[]){
		$this->logHeader->critical($message,$context);
	}
	//ALERT （550）：必须立即处理。
	public function alert($message,$context=[]){
		$this->logHeader->alert($message,$context);
	}
	//EMERGENCY （600）：紧急情况：系统无法使用。
	public function emergency($message,$context=[]){
		$this->logHeader->emergency($message,$context);
	}
	//手动close buffer
	public function bufferClose(){
		$this->emailbuffer->close();
		$this->buffer->close();
	}
}