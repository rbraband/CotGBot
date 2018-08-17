<?php
    namespace CotG\Bot\Observer;

    abstract class Observable implements \SplSubject {
        protected $observers = [];

        public function attach(\SplObserver $observer) {
            $id                    = spl_object_hash($observer);
            $this->observers[$id] = $observer;
        }

        public function detach(\SplObserver $observer) {
            $id = spl_object_hash($observer);

            if (isset($this->observers[$id])) {
                unset($this->observers[$id]);
            }
        }

        public function notify() {
            foreach ($this->observers as $observer) {
                $observer->update($this);
            }
        }
    }