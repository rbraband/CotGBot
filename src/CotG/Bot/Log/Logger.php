<?php
    namespace CotG\Bot\Log;
    
    class Logger {
              
        static private $instance = null;
        
        static private $debug = false;
        
        const EMERG   = 0;  // Emergency: system is unusable
        const ALERT   = 1;  // Alert: action must be taken immediately
        const CRIT    = 2;  // Critical: critical conditions
        const ERR     = 3;  // Error: error conditions
        const WARN    = 4;  // Warning: warning conditions
        const NOTICE  = 5;  // Notice: normal but significant condition
        const INFO    = 6;  // Informational: informational messages
        const DEBUG   = 7;  // Debug: debug messages
        
        static public function getInstance($debug = false) {
            if (null === self::$instance) {
                $logger = new ZendLogger();
                $writer_cli = new \Zend\Log\Writer\Stream("php://output");
                if (!$debug) $writer_cli->addFilter(self::INFO);
                $logger->addWriter($writer_cli);
                
                if (BOT_EMERG_EMAIL !== '') {
                    $mail = new \Zend\Mail\Message();
                    $mail->setFrom(BOT_EMAIL);
                    $mail->addTo(BOT_EMERG_EMAIL);
                    $writer_mail = new \Zend\Log\Writer\Mail($mail);
                    $writer_mail->addFilter(self::ERR);
                    $logger->addWriter($writer_mail);
                }
                
                $writer_file = new \Zend\Log\Writer\Stream(LOG_FILE);
                $writer_file->addFilter(self::INFO);
                $logger->addWriter($writer_file);
                self::$instance = $logger;
                self::$debug = $debug;
            }
            return self::$instance;
        }
        
        public function __construct() {}
        
        private function __clone(){}
        
    }