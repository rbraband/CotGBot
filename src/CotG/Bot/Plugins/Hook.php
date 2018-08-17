<?php
    namespace CotG\Bot\Plugins;
    
    use CotG\Bot\Observer;
    
    class Hook extends Observer\Observable {
        private $command;
        private $is_command;
        private $regex;
        private $disabled;
        
        public $name;
        public $func;
        public $category;
        public $input;
        
        protected $observers= array ();
        
        public function __construct() {
            $this->disabled = false;
        }
        
        static function factory($command,
        $name,
        $is_command,
        $regex,
        $func,
        $category) {
            $hook = new Hook;
            $hook->command = $command;
            $hook->name = $name;
            $hook->is_command = ($is_command == true) ? true : false;
            $hook->regex = ($regex != '') ? $regex : "/^{$command}$/";
            $hook->func = $func;
            $hook->category = $category;
            $hook->attach($hook->category);
            return $hook;
        }
        
        public function isCommand() {
            return $this->is_command;
        }
        
        public function getName() {
            return $this->name;
        }
        
        public function callFunction($subject, $input) {
            $anonym = $this->func;
            $this->input = $input;
            $subject->debug('Hook->'.$this->name);
            if ($this->notify()) return $anonym($subject, $input, $this);
        }
        
        public function getCommand() {
            return $this->command;
        }
        
        public function compCommand($compare) {
            if ($this->isCommand()) {
                if ($compare['command'][0] != PRE) return false;
                else return preg_match($this->evalRegex(), substr($compare['command'], 1));
            } else {
                return (preg_match($this->evalRegex(), $compare['command']) || preg_match($this->evalRegex(), $compare['message']));
            }
        }
        
        private function evalRegex() {
            $regex = $this->regex;
            eval ("\$regex = \"$regex\";");
            return $regex;
        }
        
        public function notify() {
            $return = true;
            foreach ($this->observers as $obj) {
                $return = $obj->update($this);
            }
            return $return;
        }
        
        public function breakThis() {
            $return = false;
            foreach ($this->observers as $obj) {
                $return = $obj->dobreak;
            }
            return $return;
        }
    }    