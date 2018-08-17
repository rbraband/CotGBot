<?php
    namespace CotG\Bot\Cron;
    
    use CotG\Bot\Observer;
    
    class CronDaemon extends Observer\Observable {
        public $note;
        
        private $lastExec;
        
        protected $observers= array ();
        
        const TICK0    = 'cron.tick_0';
        const TICK1    = 'cron.tick_1';
        const TICK5    = 'cron.tick_5';
        const TICK10   = 'cron.tick_10';
        const TICK15   = 'cron.tick_15';
        const TICK20   = 'cron.tick_20';
        const TICK30   = 'cron.tick_30';
        const HOURLY   = 'cron.hourly';
        const DAILY    = 'cron.daily';
        const WEEKLY   = 'cron.weekly';
        const MONTHLY  = 'cron.monthly';
        const YEARLY   = 'cron.yearly';
        // Only for cleanup all cons
        const CRONALL  = 'cron.all';
        
        private $crons = array(
        array('cron.hourly',  'is',   1),
        array('cron.daily',   'Gis',  1),
        array('cron.weekly',  'wGis', 1),
        array('cron.monthly', 'jGis', 100001),
        array('cron.yearly',  'zGis', 1)
        );
        
        private $ticks = array(
        array('cron.tick_0',  's',   10),
        array('cron.tick_1',  'is',  100),
        array('cron.tick_5',  'is',  500),
        array('cron.tick_10', 'is',  1000),
        array('cron.tick_15', 'is',  1500),
        array('cron.tick_20', 'is',  2000),
        array('cron.tick_30', 'is',  3000),
        );
        
        static function factory($observer) {
            
            // New Cron Object
            $cron = new CronDaemon;
            
            // Attach observer
            $cron->attach($observer);
            
            // Return the object
            return $cron;
        }
        
        public function check() {
            $return = false;//sollte alle events zurückgeben!
            $this->lastExec = time();
            foreach($this->crons as $cron) {
                if (abs(date($cron[1], $this->lastExec) +1) == $cron[2]) {
                    $this->kick(CRON, $cron[0]);
                    $return = true;
                }
            }
            foreach($this->ticks as $tick) {
                if (!(abs(date($tick[1], $this->lastExec)) % $tick[2])) {
                    $this->kick(TICK, $tick[0]);
                    $return = true;
                }
            }
            return $return;
        }
        
        private function kick($type, $event) {
            $this->note = array('type' => $type,
            'name' => $event,
            'time' => $this->lastExec
            );
            $this->notify();
        }
        
    }    