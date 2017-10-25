<?php


class Database {
    private $driver;
    private $host;
    private $user;
    private $pass;
    private $data;
    private $dsn;
    private $pdo;

    /**
      @name Database 
      @param string driver
      @param string host
      @param string user
      @param string pass
    */
    public function __construct($driver,$host,$user,$pass) {
        $this->driver = $driver;
        $this->host = $host;
        $this->user = $user;
        $this->pass = $pass;
    }

    /**
      @name changeDatabase
      @param string Database
    */
    public function changeDatabase($data) {
        $this->data = $data;
        $this->dsn = sprintf("%s:dbname=%s;host=%s;port=3306",$this->driver,$this->data,$this->host);
        $this->connect();
    }

    private function connect() {
        $options = array(
            PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'
        );
        try {
            $this->pdo = new PDO($this->dsn,$this->user,$this->pass);
        }
        catch(PDOException $ex)
        {
            die("Fehler beim Verbinden: ".$ex->getMessage());
        }
    }

    public function getPDO() {
        return $this->pdo;
    }
}
    