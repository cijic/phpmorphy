<?php
require_once(dirname(__FILE__) . '/../source/source.php');

interface phpMorphy_Dict_Writer_Interface {
    function write(phpMorphy_Dict_Source_Interface $source);
}

interface phpMorphy_Dict_Writer_Observer_Interface {
    function onStart();
    function onLog($message);
    function onEnd();
}

class phpMorphy_Dict_Writer_Observer_Empty implements phpMorphy_Dict_Writer_Observer_Interface {
    function onStart() { }
    function onLog($message) { }
    function onEnd() { }
}

class phpMorphy_Dict_Writer_Observer_Standart implements phpMorphy_Dict_Writer_Observer_Interface {
    protected
        $start_time;

    function __construct($callback) {
        if(!is_callable($callback)) {
            throw new Exception("Invalid callback");
        }

        $this->callback = $callback;
    }

    function onStart() {
        $this->start_time = microtime(true);
    }

    function onEnd() {
        $this->writeMessage(sprintf("Total time = %f", microtime(true) - $this->start_time));
    }

    function onLog($message) {
        $this->writeMessage(sprintf("+%0.2f %s", microtime(true) - $this->start_time, $message));
    }

    protected function writeMessage($msg) {
        call_user_func($this->callback, $msg);
    }
}

abstract class phpMorphy_Dict_Writer_Base {
    private $observer;

    function __construct() {
        $this->setObserver(new phpMorphy_Dict_Writer_Observer_Empty());
    }

    function setObserver(phpMorphy_Dict_Writer_Observer_Interface $observer) {
        $this->observer = $observer;
    }

    function hasObserver() {
        return isset($this->observer);
    }

    function getObserver() {
        return $this->observer;
    }

    protected function log($message) {
        if($this->hasObserver()) {
            $this->getObserver()->onLog($message);
        }
    }
}