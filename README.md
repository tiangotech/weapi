## [WeApi  -V1.0.0]

WeApi 的目的是使接口开放更为简单方便，参考了其他框架，只保留其对接口开发有帮助的思想，尽可能的使框架轻盈
+ 集成了各个支付的统一接口（微信，支付宝，手机厂商，米大师）,容易横向扩展
+ 集成了各个登入接口（微信，支付宝，手机厂商，米大师）,容易横向扩展
+ 封装了微信大部分接口
+ 基于monolog & 邮件系统实现日志系统和报错系统
+ 错误处理机制，解决接口开发时错误难以第一时间发现，错误抓取困难
+ 基于memcached纯PHP实现多进程消息列队

目前 WeApi 还属于初级版本希望加入我们一起携手完善框架

### 主要目录结构
```
.
│
├── accessToken     //微信accesstoken存放目录（如果是负载均衡可考虑缓存）
├── config          //项目接口公共配置（支付等一些参数配置都在这）
│   └── cert        //支付证书存放目录
|   └── AlipayConfig.php//阿里支付相关参数
|   └── MdspayConfig.php//应用宝米大师支付相关参数
|   └── OfficiapayConfig.php//手机厂商支付相关参数
|   └── PayConfig        //微信支付相关参数
|   └── WechatApiConfig.php//微信接口相关的参数
|   └── BaisonConfig.php  //百迅内部接口，数据库等相关配置
|   └── VersionConfig.php  //接口版本控制配置
|    
│
├── log             //日志保存目录（注意设置目录权限）
│
├── model           //主要业务
│
├── runerr          //系统错误时邮件报错间隔设置目录（注意设置目录权限）
├── util            //工具类
├── vendor          //三方插件目录
├── wemq            //消息列队层
│
│── alipaynotify.php//阿里支付成功后接收回调业务处理
|── offocialpatnotify.php//手机厂商支付成功后接收回调业务处理
|── paynotify.php   //微信支付成功后接收回调业务处理
|── index.php       //统一入口文件
|── App.php         //框架加载文件
|── ErrorCode.php   //错误信息配置
|__ start.php       //消息列队启动文件

```
+ [微信授权文件统一存放根目录下]

### 接口开发说明
+ 所有业务接口都在model层下，命名方式 TestModel.php（支持在model层新建业务层目录如User）可参考Test
```
│
├── model           //主要业务
│   └── Test        //分类业务
```
+ 使用Log类的时候可以自定义报错接收邮件
```
use weapi\util\Log;
class test {
    protected $log;
    public function __construct(){
        $this->log = new Log("emailtest",1,["1406835034@qq.com"]);
        //第一个参数是通道系统会自动根据该参数分类日志并在log下创建文件存储
        //第二个参数为日志保存天数,第三个参数为接收系统邮件的邮件地址 参数类型 array
    }
    public function emailtest(int $uid){
        $this->log->error("test");
    }
}
```
```
日志系统分八个等级,error及error以上的等级系统自动会发送通知邮件
```
+ 接口的请求参数及函数的参数类型
```
public function emailtest(int $uid,string $name,float $coin, bool $isok,$param=""){
    //函数设置的参数就是接口需要传的参数，有默认值的话不是必须传的，
    //另外可以设置参数类型系统将自动过滤，(php7.0一下版本基本变量类型不可用)
}
```
+ 接口的公共参数
```
d （对应model内目录） m（对应model内类文件名）a（对应model内文件类的方法名）
```
```
+ 接口版本控制
 +----------------------------------------------------------------------
 | 配置说明
 +----------------------------------------------------------------------
 | d（目录层）  D=>DV2,... 版本统一 V加数字
 +----------------------------------------------------------------------
 | m（类文件层）D=>array(M=>MV2,...) D不是必须，版本统一 V加数字
 +----------------------------------------------------------------------
 | a（方法层）  D=>array(M=>array(A=>AV2,...),...) D不是必须，版本统一 V加数字
 +----------------------------------------------------------------------
```
```
return [
    "d" => array(
        "Test" => "TestV2",
    ),
    "m" => array(
        "Test" => array(
            "test" => "testV2",
        ),
        "test" => "testV2",
    ),
    "a" => array(
        "Test" => array(
            "test"=>array(
                "test"=>"testV2",
            ),
        ),
        "test"=>array(
            "test"=>"testV2",
        ),
    ),
];
```

### 开发时注意model内以外文件都是公共文件，修改是必须注意测试，（测试代码部署在newecho/newweapi）

### 相关业务及接口信息
