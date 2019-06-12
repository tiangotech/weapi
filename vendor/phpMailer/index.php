<?php
require_once("./functions.php");

// $userName=trim($_GET['name']);
// $certno=trim($_GET['certno']);
// $mobile=trim($_GET['mobile']);
// $address=trim($_GET['address']);

// $datetime = date("Y-m-d h:i:s", time()); //时间


// //接受邮件的邮箱地址
// //$email='x001@qq.com';
// //多邮件示例
// $email=array("x001@qq.com","x002@qq.com","x003@qq.com");

// //$subject为邮件标题
// $subject = $userName.'的测试邮件，来自XXX网站';

// //$content为邮件内容
// $content="<div><b>".$userName."</b></div>";


//执行发信
$flag = sendMail();


//判断是否重复提交！
if($flag)
{
    //发送成功
    $data =  "{\"errCode\":\"0000\",\"dtime\":\"{$datetime}\"}";
    echo json_encode($data);
    exit();
}else{
    //发送失败
    $data =  "{\"errCode\":\"9999\",\"dtime\":\"{$datetime}\"}";
    echo json_encode($data);
    exit();

}