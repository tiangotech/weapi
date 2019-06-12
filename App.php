<?php 
namespace weapi;

use weapi\ErrorCode;
use weapi\wemq\MemcMQ;
use weapi\util\Request;
use weapi\util\Tools;
use weapi\util\Job;
use weapi\util\Log;
use weapi\config\BaisonConfig;
class App{
    //=======================================
    /*
        级别常量    错误值    错误报告描述
       E_ERROR 1  致命的运行时错误（阻止脚本执行）
       E_WARNING   2  运行时警告(非致命性错误)
       E_PARSE 4  从语法中解析错误
       E_NOTICE    8  运行时注意消息(可能是或可能不是一个问题)
       E_CORE_ERROR    16 PHP启动时初始化过程中的致命错误
       E_CORE_WARNING  32 PHP启动时初始化过程中的警告(非致命性错)
       E_COMPILE_ERROR 64 编译时致命性错
       E_COMPILE_WARNING   128    编译时警告(非致命性错)
       E_USER_ERROR    256    用户自定义的致命错误
       E_USER_WARNING  512    用户自定义的警告(非致命性错误)
       E_USER_NOTICE   1024   用户自定义的提醒(经常是bug)
       E_STRICT    2048   编码标准化警告(建议如何修改以向前兼容)
       E_ALL   6143   所有的错误、警告和注意信息
    */
    public static function exception_error(){
        $log = new Log("RUN",30);
        set_exception_handler(array($log, 'my_exception_handler'));//未捕获exception类的异常
        set_error_handler(array($log, 'my_error_handler'),E_ALL);//截获error类的错误(不能捕获级别1或4) 默认（E_ALL）  此处切不可掉换位置
        register_shutdown_function(array($log,"my_error_catch"));
    }
	public static function run(){
		$request = new Request();
        //===============授权验证规则==========
        $rel_auth = self::auth($request);
        //=====================================
        if($rel_auth !== true){
            self::dealWithResult($rel_auth);
        }
		$m = isset($request->param['m'])?$request->param['m']:"";
        $c = isset($request->param['c'])?$request->param['c']:"";
		$a = isset($request->param['a'])?$request->param['a']:"index";
        //============接口版本控制===========
        if(defined('V_CONFIG')){
            $v_config = include V_CONFIG;
            if(isset($v_config['m'][$m])){
                $m = $v_config['m'][$m];
            }
            if ($m) {
                if(isset($v_config['c'][$m][$c])){
                    $c = $v_config['c'][$m][$c];
                }
            }else{
                if(isset($v_config['c'][$c])){
                    $c = $v_config['c'][$c];
                }
            }
            if ($m) {
                if(isset($v_config['a'][$m][$c][$a])){
                    $a = $v_config['a'][$m][$c][$a];
                }
            }else{
                if(isset($v_config['a'][$c][$a])){
                    $a = $v_config['a'][$c][$a];
                }
            }
        }
        //===================================
		$model = self::invokeClass($m,$c,$request->param);
        if(!method_exists($model,$a)){
            self::dealWithResult("$a method is not exist！");
        }

		$reflect = new \ReflectionMethod($model,$a);
		$args = self::bindParams($reflect,$request->param);
		$rel = $reflect->invokeArgs(isset($model) ? $model : null, $args);
		//==========日志监控===============================
		
		// $log->warning("");
		//=======================================
		self::dealWithResult($rel);
	}
    
    /**
     * 接口受权
     * @access public
     * @param objext        $request 
     * @return mixed
     */
    public static function auth($request){
        //============授权验证规则==================
        return true;
    }
	/**
     * 结果处理
     * @access public
     * @param mixed  $result  运行结果
     * @return josn
     */
    public static function dealWithResult($result){
        $err_data = [];
    	if ($result) {
        		if ($result === true) {
        			Tools::httpok([],0);
        		}elseif (is_array($result)){
                    
                    if (isset($result['errorCode'])) {
                        $msg = $result['errorMsg'];
                        unset($result['errorMsg']);
                        $errorcode = $result['errorCode'];
                        unset($result['errorCode']);
                        $errorMsg = isset(ErrorCode::$ErrCode[$errorcode])?ErrorCode::$ErrCode[$errorcode]:"ERROR";
                        Tools::httpfalse( $result,$errorcode,$msg,$errorMsg);
                    }
                    if (isset($result['errorMsg'])) {
                        $errorMsg = $result['errorMsg'];
                        unset($result['errorMsg']);
                        $errorCode = 40000;
                        Tools::httpfalse( $result,$errorCode,$errorMsg,$errorMsg);
                    }
                    if (isset($result['okMsg'])) {
                        $okMsg = $result['okMsg'];
                        unset($result['okMsg']);
                        Tools::httpok($result,0,$okMsg);
                    }
        			Tools::httpok($result,0,"SUCCESS");
        		}else{
                    if (is_numeric($result)) {
                        $msg = isset(ErrorCode::$ErrCode[$result])?ErrorCode::$ErrCode[$result]:"ERROR";
                        $errorCode = $result;
                    }else{
                        $msg = $result?$result:"ERROR";
                        $errorCode = 40000;
                    }
        			Tools::httpfalse([],$errorCode,$msg,$msg);
        		}
    	}else{
    		Tools::httpfalse([],40000,"ERROR","ERROR");
    	}
    }
	/**
     * 调用反射执行类的实例化 支持依赖注入
     * @access public
     * @param string $class 类名
     * @param array  $vars  变量
     * @return mixed
     */
    public static function invokeClass($m, $c, $vars = []){
        if ($m) {
            $class = "weapi\\model\\".$m."\\".$c;
        }else{
            $class = "weapi\\model\\".$c;
        }
        // die($class);
        $reflect     = new \ReflectionClass($class);
        $constructor = $reflect->getConstructor();//映射构造函数
        $args        = $constructor ? self::bindParams($constructor, $vars) : [];
        return $reflect->newInstanceArgs($args);//实例化类
    }

    /**
     * 绑定参数
     * @access private
     * @param \ReflectionMethod|\ReflectionFunction $reflect 反射类
     * @param array                                 $vars    变量
     * @return array
     */
    private static function bindParams($reflect, $vars = []){
        $args = [];
        if ($reflect->getNumberOfParameters() > 0) {
            reset($vars);
            $type = key($vars) === 0 ? 1 : 0;
            foreach ($reflect->getParameters() as $param) {
                $args[] = self::getParamValue($param, $vars, $type);
            }
        }

        return $args;
    }
    /**
     * 获取参数值
     * @access private
     * @param \ReflectionParameter  $param 参数
     * @param array                 $vars  变量
     * @param string                $type  类别
     * @return array
     */
    private static function getParamValue($param, &$vars, $type){
        $result =array();
        $name  = $param->getName();
        $class = "";
        if (substr(PHP_VERSION, 0, 5) >= '7.0.0') {
            $class = $param->getClass();
        }

        if (in_array("getType",get_class_methods($param))) {
            if (substr(PHP_VERSION, 0, 5) < '7.1.0') {
                $paramType = $param->getType();
            }else{
                if (is_object($param->getType())) {
                    $paramType = $param->getType()->getName();
                }
                $paramType = "mixed";
            }
        }else{
            $paramType = "mixed";
        }
        //======参数类型判断过滤==========
        if(self::checkParamType($vars,$name,$paramType)===false){
            self::dealWithResult(array('errorMsg'=>"参数 $name 是 $paramType 类型" ,"errorCode"=>40002));
        }
        //============================
        if ($class) {
            $className = $class->getName();
            $request = new Request();
            $bind = $request->param[$name];
            if ($bind instanceof $className) {
                $result = $bind;
            } else {
                if (method_exists($className, 'invoke')) {
                    $method = new \ReflectionMethod($className, 'invoke');

                    if ($method->isPublic() && $method->isStatic()) {
                        return $className::invoke(Request::instance());
                    }
                }
                $result = method_exists($className, 'instance') ?
                $className::instance() :
                new $className;
            }
        } elseif (1 == $type && !empty($vars)) {
            $result = array_shift($vars);
        } elseif (0 == $type && isset($vars[$name])) {
            $result = $vars[$name];
        } elseif ($param->isDefaultValueAvailable()) {
            $result = $param->getDefaultValue();
        } else {
            // throw new \InvalidArgumentException('method param miss:' . $name);
            self::dealWithResult('method param miss:' . $name);
        }

        return $result;
    }
    /**
     * 验证参数类型，并过滤非法字符
     * @access private
     * @param string  $name  参数名
     * @param array                 $vars  变量
     * @param string                $paramType  类型
     * @return bool
     */
    private static function checkParamType(&$vars,$name,$paramType){
        if (!isset($vars[$name])) {
            return true;
        }
        $vars[$name] = filter_var($vars[$name],FILTER_SANITIZE_MAGIC_QUOTES);
        $vars[$name] = filter_var($vars[$name],FILTER_SANITIZE_SPECIAL_CHARS);
        switch ($paramType) {
            case 'bool':
                $vars[$name] = filter_var($vars[$name],FILTER_VALIDATE_BOOLEAN);
                return true;
                break;
            case 'int':
                if(filter_var($vars[$name],FILTER_VALIDATE_INT)){
                    $vars[$name] = filter_var($vars[$name],FILTER_SANITIZE_NUMBER_INT);
                    return true;
                }else{
                    return false;
                }
                break;
            case 'string':
                $vars[$name] = filter_var($vars[$name],FILTER_SANITIZE_STRIPPED);
                return true;
                break;
            case 'float':
                if(filter_var($vars[$name],FILTER_VALIDATE_FLOAT)){
                    $vars[$name] = filter_var($vars[$name],FILTER_SANITIZE_NUMBER_FLOAT);
                    return true;
                }else{
                    return false;
                }
                
                break;
            case 'mixed':
                return true;
                break;
        }
    }
}

