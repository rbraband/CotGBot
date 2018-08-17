<?php
    namespace CotG\Bot\Observer;

    abstract class Observer implements \SplObserver {
        private $observer;

        function __construct(\SplSubject $observer) {
            $this->observer = $observer;
            $this->observer->attach($this);
        }
        
        public function update(\SplSubject $subject) {
            // need to implement
            return true;
        }
    }