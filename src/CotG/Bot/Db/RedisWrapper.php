<?php
    
    namespace CotG\Bot\Db;
    
    define('WITHSCORES', true);
    
    class RedisWrapper implements LoggerAwareInterface {
        // Singleton instance
        private static $instance;
        // Redis instance
        private $redis;
        // Redis connect status
        private $connect;
        // Redis PID
        private $pid;
        // Logger
        protected $logger;
        
        public function setLogger(LoggerInterface $logger) {
            $this->logger = $logger;
        }
        
        // private constructor function
        // to prevent external instantiation
        private function __construct($pid = 0) {
            if (class_exists('Redis')) {
                try {
                    $this->redis   = new Redis(); // needs https://github.com/nicolasff/phpredis
                    $this->connect = $this->redis->connect(REDIS_CONNECTION);
                    $this->select(REDIS_DB);
                    $this->redis->setOption(Redis::OPT_PREFIX, REDIS_NAMESPACE);
                    $this->pid = $pid;
                    } catch (RedisException $e) {
                    $this-logger->err("Redis connect Error: " . $e->getMessage()));
                    return false;
                }
            }
        }
        
        // destructor function
        function __destruct() {
            unset($this->redis);
            $this->connect = false;
            $this->pid     = null;
        }
        
        // getInstance method
        public static function getInstance($prozessor = null) {
            if (is_null($prozessor)) $prozessor = posix_getpid();
            if (!self::$instance[$prozessor]) {
                self::$instance[$prozessor] = new self($prozessor);
            }
            
            return self::$instance[$prozessor];
        }
        
        // getInstance method
        public function reInstance($prozessor = null) {
            if (is_null($prozessor)) $prozessor = posix_getpid();
            $this->__construct($prozessor);
        }
        
        // testPid method
        public static function testPid($prozessor = null) {
            if (is_null($prozessor)) $prozessor = posix_getpid();
            if (!self::$instance[$prozessor]) {
                return false;
            }
            
            return (self::$instance[$prozessor]->pid == $prozessor) ? $prozessor : false;
        }
        
        //...
        
        // Call a dynamically wrapper...
        public function __call($method, $args) {
            if (method_exists($this->redis, $method)) {
                try {
                    return call_user_func_array(array($this->redis, $method), $args);
                    } catch (RedisException $e) {
                    $line = trim(date("[d/m @ H:i:s]") . "Redis command ('{$method}') Error: " . $e->getMessage()) . "\n";
                    error_log($line, 3, REDIS_LOG_FILE);
                    
                    return false;
                }
                } else {
                return false;
            }
        }
        
        // Return an error
        public function status() {
            return ($this->connect && $this->PING() == '+PONG') ? true : false;
        }
        
        // Overwrite redis->getKeys
        public function getKeys($pattern) {
            $keys = $this->redis->keys($pattern);
            foreach ($keys as $_k => $_v) {
                if (strpos($_v, REDIS_NAMESPACE) === 0) $keys[$_k] = substr($_v, strlen(REDIS_NAMESPACE));
            }
            
            return $keys;
        }
        
        // Key without REDIS_NAMESPACE and PATTERN
        public function clearKey($keys, $pattern = '//', $limit = -1) {
            if (!is_array($keys)) {
                $force_array = true;
                $keys        = array($keys);
            } else $force_array = false;
            if (!is_array($pattern)) $pattern = array($pattern);
            foreach ($keys as $_k => $_v) {
                if (strpos($_v, REDIS_NAMESPACE) === 0) $keys[$_k] = substr($_v, strlen(REDIS_NAMESPACE));
            }
            $_keys = preg_replace($pattern, '', $keys, $limit);
            if (count($_keys) == 1 && $force_array) return $_keys[0]; else return $_keys;
        }
    }
    
    // get instance of redis db
$redis = RedisWrapper::getInstance();