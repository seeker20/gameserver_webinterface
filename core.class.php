<?php
class Core {
    private static $instance = null;
    private $interfaceDB = null;
    private $dotlanDB = null;
    
    public final static function getInstance() {
        return self::$instance;
    }

    public final function getInterfaceDB() {
        return $this->interfaceDB;
    }

    public final function getDotlanDB() {
        return $this->dotlanDB;
    }

    public function setInterfaceDB($db) {
        $this->interfaceDB = $db;
    }

    public function setDotlanDB($db) {
        $this->dotlanDB = $db;
    }

    public function __construct() {
        self::$instance = $this;
        return $this;
    }
    
}