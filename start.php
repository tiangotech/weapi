<?php

require_once __DIR__ . '/autoload.php';
use weapi\wemq\Worker;
use weapi\wemq\Timer;
use weapi\wemq\MemcMQ;
use weapi\config\BaisonConfig;
use weapi\model\LuckyFlop;
use weapi\util\Job;
use weapi\util\Log;
use Monolog\Handler\SmtpMailerHandler;
$worker = new Worker();
 
$worker->count = 2;
 
$worker->onWorkerStart = function($worker){
    Timer::init();
    Timer::add(1,function(){
    	$memmq =  new MemcMQ('mq',$expire='',$config =array("host"=>BaisonConfig::MEMCACHE_HOST,"port"=>BaisonConfig::MEMCACHE_POST));
        if (!is_object($memmq)) {
            Worker::log("MemcMQ can not connect");
        }
    	$jobArr = $memmq->get(1);
  		$pid = posix_getpid();
    	if ($jobArr && $job = reset($jobArr)) {
    		/****
    		$job =array(
    			"job"=>"job",
				"class"=>"class",
				"method"=>"method",
				"args"=>array()
    		);
    		*/
	       	$job = json_decode($job,true);
                // 格式如果是正确的，则尝试执行对应的类方法
                if(isset($job['class']) && isset($job['method']) && isset($job['args'])){
                    $class_name = $job['class'];
                    $method = $job['method'];
                    // echo $class_name."->".$method."\n";
                    Worker::log($class_name."->".$method);
                    $args = (array)$job['args'];
                    if(class_exists($class_name)){
                        $class = new $class_name;
                        $callback = array($class, $method);
                        if(is_callable($callback)){
                            call_user_func_array($callback, $args);
                        }else{
                            // echo "$class_name::$method not exist\n";
                            Worker::log("$class_name::$method not exist");
                        }
                    }
                    else{
                        // echo "$class_name not exist\n";
                        Worker::log("$class_name not exist");
                    }
                }else{
                    // echo "unknow job\n";
                    Worker::log("unknow job");
                }
	    }else{
	    	// echo date('Y-m-d H:i:s') .' pid : ' . $pid.' deal with job : no job'.PHP_EOL;

	    }
    },[],true);

    Timer::tick();
};
Worker::runAll();
