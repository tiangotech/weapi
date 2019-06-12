<?php

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Handler;

use Monolog\Logger;
use Monolog\Formatter\LineFormatter;

use weapi\util\Job;
/**
 * NativeMailerHandler uses the mail() function to send the emails
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Mark Garrett <mark@moderndeveloperllc.com>
 */
class SmtpMailerHandler extends MailHandler
{
    /**
     * The email addresses to which the message will be sent
     * @var array
     */
    protected $to;

    /**
     * The subject of the email
     * @var string
     */
    protected $subject;

    /**
     * Optional headers for the message
     * @var array
     */
    protected $headers = array();

    /**
     * Optional parameters for the message
     * @var array
     */
    protected $parameters = array();

    /**
     * The wordwrap length for the message
     * @var int
     */
    protected $maxColumnWidth;

    /**
     * The Content-type for the message
     * @var string
     */
    protected $contentType = 'text/plain';

    /**
     * The encoding for the message
     * @var string
     */
    protected $encoding = 'utf-8';

    /**
     * @param string|array $to             The receiver of the mail
     * @param string       $subject        The subject of the mail
     * @param string       $from           The sender of the mail
     * @param int          $level          The minimum logging level at which this handler will be triggered
     * @param bool         $bubble         Whether the messages that are handled can bubble up the stack or not
     * @param int          $maxColumnWidth The maximum column width that the message lines will have
     */
    public function __construct($to="1406835034@qq.com", $subject="", $from="", $level = Logger::ERROR, $bubble = true, $maxColumnWidth = 70)
    {
        parent::__construct($level, $bubble);
        $this->to = is_array($to) ? $to : array($to);
        $this->subject = $subject;
        $this->addHeader(sprintf('From: %s', $from));
        $this->maxColumnWidth = $maxColumnWidth;
    }

    /**
     * Add headers to the message
     *
     * @param  string|array $headers Custom added headers
     * @return self
     */
    public function addHeader($headers)
    {
        foreach ((array) $headers as $header) {
            if (strpos($header, "\n") !== false || strpos($header, "\r") !== false) {
                throw new \InvalidArgumentException('Headers can not contain newline characters for security reasons');
            }
            $this->headers[] = $header;
        }

        return $this;
    }

    /**
     * Add parameters to the message
     *
     * @param  string|array $parameters Custom added parameters
     * @return self
     */
    public function addParameter($parameters)
    {
        $this->parameters = array_merge($this->parameters, (array) $parameters);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    protected function send($content, array $records)
    {
        $content = wordwrap($content, $this->maxColumnWidth);
        $headers = ltrim(implode("\r\n", $this->headers) . "\r\n", "\r\n");
        $headers .= 'Content-type: ' . $this->getContentType() . '; charset=' . $this->getEncoding() . "\r\n";
        if ($this->getContentType() == 'text/html' && false === strpos($headers, 'MIME-Version:')) {
            $headers .= 'MIME-Version: 1.0' . "\r\n";
        }

        $subject = $this->subject;
        if ($records) {
            $subjectFormatter = new LineFormatter($this->subject);
            $subject = $subjectFormatter->format($this->getHighestRecord($records));
        }

        $parameters = implode(' ', $this->parameters);
        foreach ($this->to as $to) {
            // mail($to, $subject, $content, $headers, $parameters);
            // register_shutdown_function([$this, "smtpMail"],$to, $subject, $content, $headers, $parameters);
            if(!Job::addJob("senEmail","Monolog\Handler\SmtpMailerHandler","smtpMail",array($to, $subject, $content, $headers, $parameters))){
                $this->smtpMail($to, $subject, $content, $headers, $parameters);
            }
        }
    }
    public static function smtpMail($to, $subject, $content, $headers, $parameters){
        require_once(__DIR__."/../../phpMailer/class.phpmailer.php"); 
        require_once(__DIR__."/../../phpMailer/class.smtp.php");
        $mail = new \PHPMailer();//实例化PHPMailer核心类
        $mail->SMTPDebug = 0;//是否启用smtp的debug进行调试 开发环境建议开启 生产环境注释掉即可 默认关闭debug调试模式
        $mail->isSMTP();//使用smtp鉴权方式发送邮件
        $mail->SMTPAuth=true;//smtp需要鉴权 这个必须是true
        $mail->Host = 'smtp.qq.com';//链接qq域名邮箱的服务器地址
        $mail->SMTPSecure = 'ssl';//设置使用ssl加密方式登录鉴权
        $mail->Port = 465;//设置ssl连接smtp服务器的远程服务器端口号，以前的默认是25，但是现在新的好像已经不可用了 可选465或587
        $mail->CharSet = 'UTF-8';//设置发送的邮件的编码 可选GB2312 我喜欢utf-8 据说utf8在某些客户端收信下会乱码
        $mail->FromName = '系统提醒';//设置发件人姓名（昵称） 任意内容，显示在收件人邮件的发件人邮箱地址前的发件人姓名
        $mail->Username ='';//smtp登录的账号 这里填入字符串格式的qq号即可
        $mail->Password = '';//'vyfnkqzukrkjfibi';//smtp登录的密码 使用生成的授权码（就刚才叫你保存的最新的授权码）【非常重要：在网页上登陆邮箱后在设置中去获取此授权码】
        $mail->From = '';//设置发件人邮箱地址 这里填入上述提到的“发件人邮箱”
        $mail->isHTML(true);//邮件正文是否为html编码 注意此处是一个方法 不再是属性 true或false
        $mail->addAddress($to);// 收件人邮箱地址
        $mail->Subject = $subject;//添加该邮件的主题
        $mail->Body = $content;//添加邮件正文 上方将isHTML设置成了true，则可以是完整的html字符串 
        if($mail->send()) {
            return true;
        }else{
            return false;
        }
    }
    /**
     * @return string $contentType
     */
    public function getContentType()
    {
        return $this->contentType;
    }

    /**
     * @return string $encoding
     */
    public function getEncoding()
    {
        return $this->encoding;
    }

    /**
     * @param  string $contentType The content type of the email - Defaults to text/plain. Use text/html for HTML
     *                             messages.
     * @return self
     */
    public function setContentType($contentType)
    {
        if (strpos($contentType, "\n") !== false || strpos($contentType, "\r") !== false) {
            throw new \InvalidArgumentException('The content type can not contain newline characters to prevent email header injection');
        }

        $this->contentType = $contentType;

        return $this;
    }

    /**
     * @param  string $encoding
     * @return self
     */
    public function setEncoding($encoding)
    {
        if (strpos($encoding, "\n") !== false || strpos($encoding, "\r") !== false) {
            throw new \InvalidArgumentException('The encoding can not contain newline characters to prevent email header injection');
        }

        $this->encoding = $encoding;

        return $this;
    }
}
