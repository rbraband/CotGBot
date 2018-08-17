<?php
    
    namespace CotG\Bot\Log;
    
    class ZendLogger extends \Zend\Log\Logger {
        
        static private $debug = false;
        
        public function __construct($debug = false) { 
            self::$debug = (bool) $debug;
            parent::__construct(); 
        }
        
        // shorthand for debug
        public function debug($message, $extra = array()) {
            if (self::$debug) {
                $this->log(Logger::DEBUG, $message, $extra);
            }
        }
        
        // shorthand for error
        public function error($message, $extra = array()) {
            $this->log(Logger::ERR, $message, $extra);
        }
    }    