<?php
namespace weapi\wemq;
class Timer
{
    public static $tasks = array();
 
    public static function init()
    {
        pcntl_signal(SIGALRM, array( __CLASS__, 'signalHandle'), false);
    }
 
    public static function signalHandle()
    {
        pcntl_alarm(1);
 
        if (empty(self::$tasks)) {
            return;
        }
        //执行任务
        foreach (self::$tasks as $run_time => $task) {
            $time_now = time();
            if ($time_now >= $run_time) {
                $func = $task[0];
                $args = $task[1];
                $interval = $task[2];
                $persistent = $task[3];
                call_user_func_array($func, $args);
                unset(self::$tasks[$run_time]);
                if($persistent){
                    Timer::add($interval, $func, $args,$persistent);
                }
            }
        }
    }
 
    /**
     * @param $interval 几秒后执行
     * @param $func 要执行的回调方法
     * @param array $args 参数
     * @param bool $persistent 是否持续执行
     * @return bool
     */
    public static function add($interval, $func, $args = array(),$persistent = true)
    {
        if ($interval <= 0) {
            echo new Exception('wrong interval');
            return false;
        }
        if (!is_callable($func)) {
            echo new Exception('not callable');
            return false;
        } else {
            $runtime = time() + $interval;
            self::$tasks[$runtime] = array($func, $args, $interval,$persistent);
            return true;
        }
    }
 
    public static function tick()
    {
        pcntl_alarm(1);
    }
}
