<?php

/**
*消息队列任务类
*@author hedongji
*@since 2019-01-13 build
*/
namespace weapi\util;

use weapi\wemq\MemcMQ;
use weapi\config\BaisonConfig;
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
class Job {
	protected $buffer;
	/**
	* 添加处理任务
	* @access public
    * @param string  $name   任务（名称）
    * @param string  $class  类名
    * @param string  $method 方法名
    * @param array   $args   方法参数
    */
	public static function addJob($name="mq",$class,$method,$args){
		if (self::checkWeWorker() == false) {
			return false;
		}
		$name="mq";
		$memmq =  new MemcMQ($name,$expire='',$config =array("host"=>BaisonConfig::MEMCACHE_HOST,"port"=>BaisonConfig::MEMCACHE_POST));
		$jobArr =array(
    		"job"=>$name,
			"class"=>$class,//"weapi\model\class",
			"method"=>$method,
			"args"=>$args
    	);
		$job = json_encode($jobArr);
		$rel = $memmq->add($job);
		if ($rel === false) {
			return false;
		}
		return true;
	}
	public static function checkWeWorker(){
        $cmd = 'ps axu|grep "weworker"|grep -v "grep"|wc -l';
        $ret = shell_exec("$cmd");
        $ret = rtrim($ret, "\r\n");
        if($ret == "0") {
            return false;
        }
        return true;
	}
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
	public static function log($channel="log",$message="",$level=250,$extra=array()){
		$log = new Logger($channel);
		//堆栈句柄
		//notice日志记录
		// $maxFiles = 30;
		// if ($channel == "FFLPX") {
		// 	$maxFiles = 7;
		// }
  		// $rotatingFileHandler = new RotatingFileHandler(__DIR__."/../log/".$channel.'/'.$channel.'.log', $maxFiles);
  		// $output = "%datetime% : %channel%.%level_name% > %message% %context% %extra%\r\n";
  		// $dateFormat = "[Y-m-d H:i:s]";
  		// $formatter = new LineFormatter($output, $dateFormat);
    //     $rotatingFileHandler->setFormatter($formatter);
        // $buffer = $buffer?$buffer:new BufferHandler($rotatingFileHandler,1,Logger::DEBUG,true,true);
        // $log->pushHandler($rotatingFileHandler);

        //errorr日志邮件提醒
        $smtpMailerHandler = new SmtpMailerHandler(["1406835034@qq.com"], "$channel", "Log@log.com",Logger::CRITICAL);
  		$htmlFormatter = new HtmlFormatter();
        $smtpMailerHandler->setFormatter($htmlFormatter);
        $smtpMailerHandler->pushProcessor(new WebProcessor());//将当前请求URI，请求方法和客户端IP添加到日志记录中
        $smtpMailerHandler->pushProcessor(new MemoryUsageProcessor());//将当前内存使用量添加到日志记录中
        $smtpMailerHandler->pushProcessor(new MemoryPeakUsageProcessor());//将峰值内存使用量添加到日志记录中
        // $smtpMailerHandler->pushProcessor(new IntrospectionProcessor());//添加发起日志调用的行/文件/类/方法

        // $buffer = $buffer?$buffer:new BufferHandler($smtpMailerHandler,5,Logger::DEBUG,true,true);
  		// $buffer = $buffer?$buffer:new DeduplicationHandler($smtpMailerHandler,null,Logger::ERROR,60);
  		$log->pushHandler($smtpMailerHandler);
	  		switch ($level) {
	  			case 100:
	  				$log->debug($message);
	  				break;
	  			case 200:
	  				$log->info($message);
	  				break;
	  			case 250:
	  				$log->notice($message);
	  				break;
	  			case 300:
	  				$log->warning($message);
	  				break;
	  			case 400:
	  				$log->error($message);
	  				break;
	  			case 500:
	  				$log->critical($message);
	  				break;
	  			case 550:
	  				$log->alert($message);
	  				break;
	  			case 600:
	  				$log->emergency($message);
	  				break;
	  		}
	  	return ;
	}
}