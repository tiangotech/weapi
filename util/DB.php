<?php
/**
*DB类
*@author hedongji
*@since 2019-01-23 build
*/
//$db = new DB();
        //$db->bindMore(array("userid"=>"3171155"));//绑定参数  
        // $rel = $db->query("SELECT * from active.qcs_log WHERE userid = :userid", null, \PDO::FETCH_NUM);//结果集key改为数字下标
        // $rel = $db->single("SELECT `date` from active.qcs_log WHERE id = :id", array("id"=>18));//获得单个字段的值
        // $rel = $db->row("SELECT * from active.qcs_log WHERE userid = :userid");//获得单行结果
        // $rel = $db->column("SELECT * from active.qcs_log WHERE userid = :userid");// Column, numeric index
namespace weapi\util;
use PDO;
use weapi\util\Log;
use weapi\config\Config;
class DB{
    private $pdo;

    private $sQuery;
    
    private $settings;
    
    private $bConnected = false;
    
    private $log;
    
    private $parameters;
    
    public function __construct($settings=array()){
        if (empty($settings)) {
            $this->settings['dbname'] = Config::DB_NAME;
            $this->settings['host'] = Config::DB_HOST;
            $this->settings['user'] = Config::DB_USER;
            $this->settings['password'] = Config::DB_PWS;
        }else{
            $this->settings = $settings;
        }
        $this->log = new Log("DB",30);
        $this->Connect();
        $this->parameters = array();
    }
    
    private function Connect(){
        $dsn = 'mysql:dbname=' . $this->settings["dbname"] . ';host=' . $this->settings["host"] . '';
        try {
            $this->pdo = new PDO($dsn, $this->settings["user"], $this->settings["password"], array(
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
            ));
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            $this->bConnected = true;
        }catch (PDOException $e) {
            echo $this->ExceptionLog($e->getMessage());
            die();
        }
    }
    public function CloseConnection(){
        $this->pdo = null;
    }
    private function Init($query, $parameters = ""){
        if (!$this->bConnected) {
            $this->Connect();
        }
        try {
            $this->sQuery = $this->pdo->prepare($query);
            $this->bindMore($parameters);
            if (!empty($this->parameters)) {
                foreach ($this->parameters as $param => $value) {
                    if(is_int($value[1])) {
                        $type = PDO::PARAM_INT;
                    } else if(is_bool($value[1])) {
                        $type = PDO::PARAM_BOOL;
                    } else if(is_null($value[1])) {
                        $type = PDO::PARAM_NULL;
                    } else {
                        $type = PDO::PARAM_STR;
                    }
                    $this->sQuery->bindValue($value[0], $value[1], $type);
                }
            }
            $this->sQuery->execute();
        }
        catch (PDOException $e) {
            echo $this->ExceptionLog($e->getMessage(), $query);
            die();
        }
        $this->parameters = array();
    }

    public function bind($para, $value){
        $this->parameters[sizeof($this->parameters)] = [":" . $para , $value];
    }

    public function bindMore($parray){
        if (empty($this->parameters) && is_array($parray)) {
            $columns = array_keys($parray);
            foreach ($columns as $i => &$column) {
                $this->bind($column, $parray[$column]);
            }
        }
    }

    public function query($query, $params = null, $fetchmode = PDO::FETCH_ASSOC){
        $query = trim(str_replace("\r", " ", $query));
        $this->Init($query, $params);
        $rawStatement = explode(" ", preg_replace("/\s+|\t+|\n+/", " ", $query));
        $statement = strtolower($rawStatement[0]);
        if ($statement === 'select' || $statement === 'show') {
            return $this->sQuery->fetchAll($fetchmode);
        } elseif ($statement === 'insert' || $statement === 'update' || $statement === 'delete') {
            return $this->sQuery->rowCount();
        } else {
            return NULL;
        }
    }
    
    public function lastInsertId(){
        return $this->pdo->lastInsertId();
    }
    
    public function beginTransaction(){
        return $this->pdo->beginTransaction();
    }
    
    public function executeTransaction(){
        return $this->pdo->commit();
    }
    
    public function rollBack(){
        return $this->pdo->rollBack();
    }
    
    public function column($query, $params = null){
        $this->Init($query, $params);
        $Columns = $this->sQuery->fetchAll(PDO::FETCH_NUM);
        $column = null;
        foreach ($Columns as $cells) {
            $column[] = $cells[0];
        }
        return $column;
    }
    
    public function row($query, $params = null, $fetchmode = PDO::FETCH_ASSOC){
        $this->Init($query, $params);
        $result = $this->sQuery->fetch($fetchmode);
        $this->sQuery->closeCursor();
        return $result;
    }
    
    public function single($query, $params = null){
        $this->Init($query, $params);
        $result = $this->sQuery->fetchColumn();
        $this->sQuery->closeCursor();
        return $result;
    }
    
    private function ExceptionLog($message, $sql = ""){
        $exception = 'Unhandled Exception. <br />';
        $exception .= $message;
        $exception .= "<br /> You can find the error back in the log.";
        if (!empty($sql)) {
            $message .= "\r\nRaw SQL : " . $sql;
        }
        $this->log->alert($message);
        return $exception;
    }
}
?>
