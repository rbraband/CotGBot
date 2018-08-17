<?php
    namespace CotG\Bot\Plugins;

    use CotG\Bot\Observer;

    class Category extends Observer\Observer {
        private $enabled;
        private $access;
        private $rules = array();

        // spezial rules options
        private $timeout;
        private $fuzzyit;
        private $schedule;
        private $randomice;
        private $spamsafe;

        public $name;
        public $dobreak;

        public function __construct() {
            $this->enabled = true;
        }

        static function factory($name, $rules, $access) {
            $category         = new Category;
            $category->name   = $name;
            $category->rules  = $rules;
            $category->access = $access;
            $category->setRules();

            return $category;
        }

        public function getName() {
            return $this->name;
        }

        public function getAccess() {
            return $this->access;
        }

        public function enable() {
            $this->enabled = true;
        }

        public function disable() {
            $this->enabled = false;
        }

        public function isEnabled() {
            return $this->enabled;
        }

        private function setRules() {
            $this->timeout   = intval(@$this->rules['timeout']);
            $this->fuzzyit   = (@$this->rules['fuzzy']) ? true : false;
            $this->schedule  = intval(@$this->rules['schedule']);
            $this->randomice = (@$this->rules['humanice']) ? true : false;
            $this->spamsafe  = (@$this->rules['spamsafe']) ? true : false;
            $this->dobreak   = (@$this->rules['dobreak']) ? true : false;
            $this->enabled   = (@$this->rules['enabled']) ? $this->rules['enabled'] : true;
        }

        public function update(\SplSubject $subject) {
            $return = ($this->isEnabled() && $this->checkAccess($subject));
            if ($return && $this->fuzzyit) $return = $this->fuzzyIt();
            if ($return && $this->spamsafe) $return = $this->spamCheck($subject);
            if ($return && $this->randomice) $return = $this->randomSleep();

            return $return;
        }

        private function randomSleep() {
            usleep(mt_rand(500, 1500) * 1000);

            return true;
        }

        private function fuzzyIt() {
            $fuzzy = mt_rand(1, 1000);
            if ($fuzzy % 2 == 0) {
                return true;
            } else {
                return false;
            }
        }

        private function checkAccess($hook) {
            return true; // later implemented
        }

        private function spamCheck($hook) {
            global $redis, $bot;
            if (!$redis->status()) return true;
            $key = "{$this->name}:spamcheck:{$hook->name}:{$hook->input['user']}";
            $ttl = $redis->ttl($key);
            $bot->log(REDIS_NAMESPACE . "{$key} TTL: {$ttl}");
            if ($ttl === -1 || $ttl === -2) {
                $bot->log("NoSPAM");
                $redis->set($key, 0, SPAMTTL);

                return true;
            } else {
                $incr = $redis->incr($key) * SPAMTTL;
                if ($hook->input['channel'] == GAMEIN) {
                    $bot->add_tellmsg("SpamCheck! ($incr sec.)", $hook->input['user']);
                } else {
                    $bot->add_privmsg("SpamCheck! ($incr sec.)", $hook->input['user']);
                }
                $redis->expire($key, $incr);
                return false;
            }
        }

    }