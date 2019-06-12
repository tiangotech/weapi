<?php
/*
 * memcache队列类
 * 支持多进程并发写入、读取
 * 边写边读,AB面轮值替换
 * @author lkk/blog.lianq.net
 * @version 0.2
 * @create on 9:25 2012-9-28
 *
 * @edited on 14:03 2013-4-28
 * 修改说明:
 *      1.修改了changeHead方法,当get(1)只取一条数据时$head_key的值没改变的问题
 *      2.修改了clear方法,当队列较小时按最大队列长度删除的问题
 *
 * 使用方法:
 *      $obj = new memcacheQueue('duilie');
 *      $obj->add('1asdf');
 *      $obj->getQueueLength();
 *      $obj->read(11);
 *      $obj->get(8);
 */
namespace weapi\wemq;
class MemcMQ{
    public static   $client;            //memcache客户端连接
    public          $access;            //队列是否可更新   
    private         $currentSide;       //当前轮值的队列面:A/B
    private         $lastSide;          //上一轮值的队列面:A/B
    private         $sideAHead;         //A面队首值
    private         $sideATail;         //A面队尾值
    private         $sideBHead;         //B面队首值
    private         $sideBTail;         //B面队尾值
    private         $currentHead;       //当前队首值
    private         $currentTail;       //当前队尾值
    private         $lastHead;          //上轮队首值
    private         $lastTail;          //上轮队尾值 
    private         $expire;            //过期时间,秒,1~2592000,即30天内
    private         $sleepTime;         //等待解锁时间,微秒
    private         $queueName;         //队列名称,唯一值
    private         $retryNum;          //重试次数,= 10 * 理论并发数
    
    const   MAXNUM      = 10000;                //(单面)最大队列数,建议上限10K
    const   HEAD_KEY    = '_lkkQueueHead_';     //队列首kye
    const   TAIL_KEY    = '_lkkQueueTail_';     //队列尾key
    const   VALU_KEY    = '_lkkQueueValu_';     //队列值key
    const   LOCK_KEY    = '_lkkQueueLock_';     //队列锁key
    const   SIDE_KEY    = '_lkkQueueSide_';     //轮值面key
    
    /**
     * 构造函数
     * @param   [queueName] string  队列名称
     * @param   [expire]    string  过期时间
     * @param   [config]    array   memcache服务器参数    
     * @return  NULL
     */
    public function __construct($queueName ='',$expire='',$config =''){
        if(empty($config)){
            self::$client = memcache_pconnect('127.0.0.1',11211);
        }elseif(is_array($config)){
            self::$client = memcache_pconnect($config['host'],$config['port']);
        }elseif(is_string($config)){
            $tmp = explode(':',$config);
            $conf['host'] = isset($tmp[0]) ? $tmp[0] : '127.0.0.1';
            $conf['port'] = isset($tmp[1]) ? $tmp[1] : '11211';
            self::$client = memcache_pconnect($conf['host'],$conf['port']);
        }
        if(!self::$client) return false;
        
        ignore_user_abort(TRUE);//当客户断开连接,允许继续执行
        set_time_limit(0);//取消脚本执行延时上限
        
        $this->access = false;
        $this->sleepTime = 1000;
        $expire = (empty($expire)) ? 3600 : (int)$expire+1;
        $this->expire = $expire;
        $this->queueName = $queueName;
        $this->retryNum = 20000;
        
        $side = memcache_add(self::$client, $queueName . self::SIDE_KEY, 'A',false, $expire);
        $this->getHeadNTail($queueName);
        if(!isset($this->sideAHead) || empty($this->sideAHead)) $this->sideAHead = 0;
        if(!isset($this->sideATail) || empty($this->sideATail)) $this->sideATail = 0;
        if(!isset($this->sideBHead) || empty($this->sideBHead)) $this->sideBHead = 0;
        if(!isset($this->sideBTail) || empty($this->sideBTail)) $this->sideBTail = 0;
    }
    
    /**
     * 获取队列首尾值
     * @param   [queueName] string  队列名称
     * @return  NULL
     */
    private function getHeadNTail($queueName){
        $this->sideAHead = (int)memcache_get(self::$client, $queueName.'A'. self::HEAD_KEY);
        $this->sideATail = (int)memcache_get(self::$client, $queueName.'A'. self::TAIL_KEY);
        $this->sideBHead = (int)memcache_get(self::$client, $queueName.'B'. self::HEAD_KEY);
        $this->sideBTail = (int)memcache_get(self::$client, $queueName.'B'. self::TAIL_KEY);
    }
    
    /**
     * 获取当前轮值的队列面
     * @return  string  队列面名称
     */
    public function getCurrentSide(){
        $currentSide = memcache_get(self::$client, $this->queueName . self::SIDE_KEY);
        if($currentSide == 'A'){
            $this->currentSide = 'A';
            $this->lastSide = 'B';  

            $this->currentHead  = $this->sideAHead;
            $this->currentTail  = $this->sideATail;
            $this->lastHead     = $this->sideBHead;
            $this->lastTail     = $this->sideBTail;         
        }else{
            $this->currentSide = 'B';
            $this->lastSide = 'A';

            $this->currentHead  = $this->sideBHead;
            $this->currentTail  = $this->sideBTail;
            $this->lastHead     = $this->sideAHead;
            $this->lastTail     = $this->sideATail;                     
        }
        
        return $this->currentSide;
    }
    
    /**
     * 队列加锁
     * @return boolean
     */
    private function getLock(){
        if($this->access === false){
            while(!memcache_add(self::$client, $this->queueName .self::LOCK_KEY, 1, false, $this->expire) ){
                usleep($this->sleepTime);
                @$i++;
                if($i > $this->retryNum){//尝试等待N次
                    return false;
                    break;
                }
            }
            return $this->access = true;
        }
        return false;
    }
    
    /**
     * 队列解锁
     * @return NULL
     */
    private function unLock(){
        memcache_delete(self::$client, $this->queueName .self::LOCK_KEY);
        $this->access = false;
    }
    
    /**
     * 添加数据
     * @param   [data]  要存储的值
     * @return  boolean
     */
    public function add($data=''){
        $result = false;
        if(empty($data)) return $result;
        if(!$this->getLock()){
            return $result;
        } 
        $this->getHeadNTail($this->queueName);
        $this->getCurrentSide();
        
        if($this->isFull()){
            $this->unLock();
            return false;
        }
        
        if($this->currentTail < self::MAXNUM){
            $value_key = $this->queueName .$this->currentSide . self::VALU_KEY . $this->currentTail;
            if(memcache_set(self::$client, $value_key, $data, false, $this->expire)){
                $this->changeTail();
                $result = true;
            }
        }else{//当前队列已满,更换轮值面
            $this->unLock();
            $this->changeCurrentSide();
            return $this->add($data);
        }

        $this->unLock();
        return $result;
    }
    
    /**
     * 取出数据
     * @param   [length]    int 数据的长度
     * @return  array
     */
    public function get($length=0){
        if(!is_numeric($length)) return false;
        if(empty($length)) $length = self::MAXNUM * 2;//默认读取所有
        if(!$this->getLock()) return false;

        if($this->isEmpty()){
            $this->unLock();
            return false;
        }
        
        $keyArray   = $this->getKeyArray($length);
        $lastKey    = $keyArray['lastKey'];
        $currentKey = $keyArray['currentKey'];
        $keys       = $keyArray['keys'];
        $this->changeHead($this->lastSide,$lastKey);
        $this->changeHead($this->currentSide,$currentKey);
        
        $data   = @memcache_get(self::$client, $keys);
        if(empty($data)) $data = array();
        foreach($keys as $v){//取出之后删除
            @memcache_delete(self::$client, $v, 0);
        }
        $this->unLock();

        return $data;
    }
    
    /**
     * 读取数据
     * @param   [length]    int 数据的长度
     * @return  array
     */
    public function read($length=0){
        if(!is_numeric($length)) return false;
        if(empty($length)) $length = self::MAXNUM * 2;//默认读取所有
        $keyArray   = $this->getKeyArray($length);
        $data   = @memcache_get(self::$client, $keyArray['keys']);
        if(empty($data)) $data = array();
        return $data;
    }
    
    /**
     * 获取队列某段长度的key数组
     * @param   [length]    int 队列长度
     * @return  array
     */
    private function getKeyArray($length){
        $result = array('keys'=>array(),'lastKey'=>null,'currentKey'=>null);
        $this->getHeadNTail($this->queueName);
        $this->getCurrentSide();
        if(empty($length)) return $result;
        
        //先取上一面的key
        $i = $result['lastKey'] = 0;
        for($i=0;$i<$length;$i++){
            $result['lastKey'] = $this->lastHead + $i;
            if($result['lastKey'] >= $this->lastTail) break;
            $result['keys'][] = $this->queueName .$this->lastSide . self::VALU_KEY . $result['lastKey'];
        }
        
        //再取当前面的key
        $j = $length - $i;
        $k = $result['currentKey'] = 0;
        for($k=0;$k<$j;$k++){
            $result['currentKey'] = $this->currentHead + $k;
            if($result['currentKey'] >= $this->currentTail) break;
            $result['keys'][] = $this->queueName .$this->currentSide . self::VALU_KEY . $result['currentKey'];
        }

        return $result;
    }
    
    /**
     * 更新当前轮值面队列尾的值
     * @return  NULL
     */
    private function changeTail(){
        $tail_key = $this->queueName .$this->currentSide . self::TAIL_KEY;
        memcache_add(self::$client, $tail_key, 0,false, $this->expire);//如果没有,则插入;有则false;
        $v = memcache_get(self::$client, $tail_key) +1;
        memcache_set(self::$client, $tail_key,$v,false,$this->expire);
    }
    
    /**
     * 更新队列首的值
     * @param   [side]      string  要更新的面
     * @param   [headValue] int     队列首的值
     * @return  NULL
     */
    private function changeHead($side,$headValue){
        $head_key = $this->queueName .$side . self::HEAD_KEY;
        $tail_key = $this->queueName .$side . self::TAIL_KEY;
        $sideTail = memcache_get(self::$client, $tail_key);
        if($headValue < $sideTail){
            memcache_set(self::$client, $head_key,$headValue+1,false,$this->expire);
        }elseif($headValue >= $sideTail){
            $this->resetSide($side);
        }
    }
    
    /**
     * 重置队列面,即将该队列面的队首、队尾值置为0
     * @param   [side]  string  要重置的面
     * @return  NULL
     */
    private function resetSide($side){
        $head_key = $this->queueName .$side . self::HEAD_KEY;
        $tail_key = $this->queueName .$side . self::TAIL_KEY;
        memcache_set(self::$client, $head_key,0,false,$this->expire);
        memcache_set(self::$client, $tail_key,0,false,$this->expire);
    }
    
    
    /**
     * 改变当前轮值队列面
     * @return  string
     */
    private function changeCurrentSide(){
        $currentSide = memcache_get(self::$client, $this->queueName . self::SIDE_KEY);
        if($currentSide == 'A'){
            memcache_set(self::$client, $this->queueName . self::SIDE_KEY,'B',false,$this->expire);
            $this->currentSide = 'B';
        }else{
            memcache_set(self::$client, $this->queueName . self::SIDE_KEY,'A',false,$this->expire);
            $this->currentSide = 'A';
        }
        return $this->currentSide;
    }
    
    /**
     * 检查当前队列是否已满
     * @return  boolean
     */
    public function isFull(){
        $result = false;
        if($this->sideATail == self::MAXNUM && $this->sideBTail == self::MAXNUM){
            $result = true;
        }
        return $result;
    }
    
    /**
     * 检查当前队列是否为空
     * @return  boolean
     */
    public function isEmpty(){
        $result = true;
        if($this->sideATail > 0 || $this->sideBTail > 0){
            $result = false;
        }
        return $result;
    }
    
    /**
     * 获取当前队列的长度
     * 该长度为理论长度，某些元素由于过期失效而丢失，真实长度小于或等于该长度
     * @return  int
     */
    public function getQueueLength(){
        $this->getHeadNTail($this->queueName);

        $sideALength = $this->sideATail - $this->sideAHead;
        $sideBLength = $this->sideBTail - $this->sideBHead;
        $result = $sideALength + $sideBLength;
        
        return $result;
    }
    

    /**
     * 清空当前队列数据,仅保留HEAD_KEY、TAIL_KEY、SIDE_KEY三个key
     * @return  boolean
     */
    public function clear(){
        if(!$this->getLock()) return false;
        $this->getHeadNTail($this->queueName);
        $AHead = $this->sideAHead;$AHead--;
        $ATail = $this->sideATail;$ATail++;
        $BHead = $this->sideBHead;$BHead--;     
        $BTail = $this->sideBTail;$BTail++;
        
        //删除A面
        for($i=$AHead;$i<$ATail;$i++){
            @memcache_delete(self::$client, $this->queueName.'A'. self::VALU_KEY .$i, 0);
        }
        
        //删除B面
        for($j=$BHead;$j<$BTail;$j++){
            @memcache_delete(self::$client, $this->queueName.'A'. self::VALU_KEY .$j, 0);
        }

        $this->unLock();
        $this->resetSide('A');
        $this->resetSide('B');
        return true;
    }
    
    /*
     * 清除所有memcache缓存数据
     * @return  NULL
     */
    public function memFlush(){
        memcache_flush(self::$client);
    }



}



