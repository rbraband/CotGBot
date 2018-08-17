<?php
    namespace CotG\Bot\Lock;
    
    class LockManager {
        private $fh;
        private $fn;
        /*
            * Konstruktor
            * 
            * @param string $filename Dateiname, der als Pseudo-Lock-File benutzt werden soll
            * @throws LockManagerRunningException, wenn bereits eine Instanz läuft
        */
        public function __construct($filename) {
            $this->fn = $filename;
            $this->fh = @fopen($this->fn, 'w');
            if (!flock($this->fh, LOCK_EX + LOCK_NB)) {
                throw new LockManagerRunningException('Bot already running!');
                } else {
                declare(ticks = 10000);
                register_tick_function(array(&$this, 'reLock'), true);
            }
        }
        
        public function reLock() {
            if (false === @get_resource_type($this->fh)) {
                $this->fh = @fopen($this->fn, 'w');
                if (!flock($this->fh, LOCK_EX + LOCK_NB)) {
                    throw new LockManagerRunningException('Can\'t reLock!');
                }
            } else flock($this->fh, LOCK_EX + LOCK_NB);
            ftruncate($this->fh, 0);
        }
        
        public function __destruct() {
            if ('stream' === @get_resource_type($this->fh)) {
                flock($this->fh, LOCK_UN);
                fclose($this->fh);
                @unlink($this->fn);
            }
        }
        
    }
    
    /**
        * Exception, die geworfen wird, wenn bereits eine Instanz läuft
    */
    class LockManagerRunningException extends \Exception {
        function __construct($strMessage, $code = 0){
            parent::__construct($strMessage, $code);
        }
    }    