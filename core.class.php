<?php
class Core {
    private static $instance = null;
    private $interfaceDB = null;

    
    public final static function getInstance() {
        return self::$instance;
    }

    public final function getInterfaceDB() {
        return $this->interfaceDB;
    }

    public function setInterfaceDB($db) {
        $this->interfaceDB = $db;
    }

    public function __construct() {
        self::$instance = $this;
        return $this;
    }
    
}