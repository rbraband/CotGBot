<?php
    
    namespace CotG\Bot\Data;
    
    use Zend\Log\LoggerAwareInterface;
    use Zend\Log\LoggerInterface;
    use CotG\Bot\Observer;
    use CotG\Bot\Json;
    use CotG\Bot\Log;
    use CotG\Bot\Data;
    use CotG\Bot;
    
    class API extends Observer\Observable implements LoggerAwareInterface {
        
        protected $logger;
        
        public $note = array();
        
        public function __construct() {
            
        }
        
        public function setLogger(LoggerInterface $logger) {
            $this->logger = $logger;
        }
        
        static function factory($observer) {
            
            // New Data Object
            $data = new API;
            
            // Attach observer
            $data->attach($observer);
            
            // Return the object
            return $data;
        }
        
        // shorthand for log
        public function log($message, $priority = Log\Logger::INFO) {
            $this->logger->log($priority, $message);
        }
        
        // shorthand for debug
        public function debug($message) {
            $this->log($message, Log\Logger::DEBUG);
        }
        
        // shorthand for error
        public function error($message) {
            $this->log($message, Log\Logger::ERR);
        }
        
        public function using($user_id, $data, $path, $debug = false) {
            global $bot, $redis;
            if (!empty($data)) $json = Json\Json::Decode($data); // second parameter to true toAssoc 
            switch ($path) {
                case '/proxy/reinforcements':
                
                    $type = "PLAYER_{$user_id}|REINFORCEMENTS";
                    $user_key = "user:{$user_id}";
                    $session_id = $json->{'s'};
                    $user_agent = $json->{'a'};

                    $url = '/overview/reinover.php';
                    $bot->log("Call {$type} with URL:{$url}", Log\Logger::DEBUG);
                    $bot->curl->get($url, [
                        CURLOPT_USERAGENT => $user_agent,
                        CURLOPT_REFERER => BOT_SERVER . '/overview/overview.php',
                        CURLOPT_COOKIE => 'sec_session_id=' . $session_id,
                        CURLOPT_COOKIESESSION => true,
                        CURLOPT_HTTPHEADER => array (
                            "Accept: */*",
                            "Host: " . parse_url(BOT_SERVER, PHP_URL_HOST),
                            "Origin: " . BOT_SERVER, 
                            "Accept-Language: de-DE,de;q=0.8,en-US;q=0.6,en;q=0.4",
                            "Cache-Control: no-cache", 
                            "Pragma: no-cache",
                            "X-Requested-With: XMLHttpRequest",
                            "pp-ss: 0",
                        )
                        ], ['id' => $user_id])->then(function (\KHR\React\Curl\Result $result) use($bot, $redis, $type, $user_key, $debug) {
                            $data = $result->getBody();
                            
                            if (!empty($data)) {
                                if ($debug) $bot->log("Got {$type} data!", Log\Logger::DEBUG);
                                $redis->HMSET("{$user_key}:data", array(
                                    'collectReinforcements' => (string) $bot->get_timestamp()
                                ));
                                $bot->analyser->using($data, $type, $debug);
                            } else {
                                $bot->log("No {$type} data received!", Log\Logger::WARN);
                            }
                            _destroy($result, $data);
                        }
                    );
                    
                    if ($debug) $bot->debug('Update reinforcements: ' . REDIS_NAMESPACE . $user_key);
                    _destroy($json, $data);
                    
                    return array(200, Json\Json::Encode(array('result'=>'success')));
                    break;
                
                case '/post/reinforcements':
                
                    $type       = "PLAYER_{$user_id}|REINFORCEMENTS";
                    $user_key   = "user:{$user_id}";
                    $session_id = $json->{'s'};
                    $user_agent = $json->{'a'};
                    $data       = $json->{'c'};

                    if (!empty($data)) {
                        if ($debug) $bot->log("Got {$type} data!", Log\Logger::DEBUG);
                        $redis->HMSET("{$user_key}:data", array(
                            'collectReinforcements' => (string) $bot->get_timestamp()
                        ));
                        $bot->analyser->using($data, $type, $debug);
                    } else {
                        $bot->log("No {$type} data received!", Log\Logger::WARN);
                    }
                    
                    if ($debug) $bot->debug('Update reinforcements: ' . REDIS_NAMESPACE . $user_key);
                    _destroy($json, $data);
                    
                    return array(200, Json\Json::Encode(array('result'=>'success')));
                    break;
                
                case '/proxy/substitute':
                
                    $type = "PLAYER_{$user_id}|SUBSTITUTE";
                    $user_key = "user:{$user_id}";
                    $session_id = $json->{'s'};
                    $user_agent = $json->{'a'};

                    $url = '/includes/gSuIn.php';
                    $bot->log("Call {$type} with URL:{$url}", Log\Logger::DEBUG);
                    $bot->curl->get($url, [
                        CURLOPT_USERAGENT => $user_agent,
                        CURLOPT_REFERER => BOT_SERVER . '/World00.php',
                        CURLOPT_COOKIE => 'sec_session_id=' . $session_id,
                        CURLOPT_COOKIESESSION => true,
                        CURLOPT_HTTPHEADER => array (
                            "Accept: */*",
                            "Host: " . parse_url(BOT_SERVER, PHP_URL_HOST),
                            "Origin: " . BOT_SERVER, 
                            "Accept-Language: de-DE,de;q=0.8,en-US;q=0.6,en;q=0.4",
                            "Cache-Control: no-cache", 
                            "Pragma: no-cache",
                            "X-Requested-With: XMLHttpRequest",
                            "Content-Encoding: " . uniqid(),
                            "pp-ss: 0",
                        )
                        ], ['id' => $user_id])->then(function (\KHR\React\Curl\Result $result) use($bot, $redis, $type, $user_key, $debug) {
                            $data = $result->getBody();
                            
                            if (!empty($data)) {
                                if ($debug) $bot->log("Got {$type} data!", Log\Logger::DEBUG);
                                $redis->HMSET("{$user_key}:data", array(
                                    'getSubstitute' => (string) $bot->get_timestamp()
                                ));
                                $bot->analyser->using($data, $type, $debug);
                            } else {
                                $bot->log("No {$type} data received!", Log\Logger::WARN);
                            }
                            _destroy($result, $data);
                        }
                    );
                    
                    if ($debug) $bot->debug('Update substitute: ' . REDIS_NAMESPACE . $user_key);
                    _destroy($json, $data);
                    
                    return array(200, Json\Json::Encode(array('result'=>'success')));
                    break;
                
                case '/post/substitute':
                
                    $type       = "PLAYER_{$user_id}|SUBSTITUTE";
                    $user_key   = "user:{$user_id}";
                    $session_id = $json->{'s'};
                    $user_agent = $json->{'a'};
                    $data       = $json->{'c'};

                    if (!empty($data)) {
                        if ($debug) $bot->log("Got {$type} data!", Log\Logger::DEBUG);
                        $redis->HMSET("{$user_key}:data", array(
                            'getSubstitute' => (string) $bot->get_timestamp()
                        ));
                        $bot->analyser->using($data, $type, $debug);
                    } else {
                        $bot->log("No {$type} data received!", Log\Logger::WARN);
                    }                    
                    if ($debug) $bot->debug('Update substitute: ' . REDIS_NAMESPACE . $user_key);
                    _destroy($json, $data);
                    
                    return array(200, Json\Json::Encode(array('result'=>'success')));
                    break;
                
                case '/proxy/cities':
                
                    $type = "PLAYER_{$user_id}|CITIES";
                    $user_key = "user:{$user_id}";
                    $session_id = $json->{'s'};
                    $user_agent = $json->{'a'};

                    $url = '/overview/citover.php';
                    $bot->log("Call {$type} with URL:{$url}", Log\Logger::DEBUG);
                    $bot->curl->get($url, [
                        CURLOPT_USERAGENT => $user_agent,
                        CURLOPT_REFERER => BOT_SERVER . '/overview/overview.php',
                        CURLOPT_COOKIE => 'sec_session_id=' . $session_id,
                        CURLOPT_COOKIESESSION => true,
                        CURLOPT_HTTPHEADER => array (
                            "Accept: */*",
                            "Host: " . parse_url(BOT_SERVER, PHP_URL_HOST),
                            "Origin: " . BOT_SERVER, 
                            "Accept-Language: de-DE,de;q=0.8,en-US;q=0.6,en;q=0.4",
                            "Cache-Control: no-cache", 
                            "Pragma: no-cache",
                            "X-Requested-With: XMLHttpRequest",
                            "pp-ss: 0",
                        )
                        ], ['id' => $user_id])->then(function (\KHR\React\Curl\Result $result) use($bot, $redis, $type, $user_key, $debug) {
                            $data = $result->getBody();
                            
                            if (!empty($data)) {
                                if ($debug) $bot->log("Got {$type} data!", Log\Logger::DEBUG);
                                $redis->HMSET("{$user_key}:data", array(
                                    'collectCities' => (string) $bot->get_timestamp()
                                ));
                                $bot->analyser->using($data, $type, $debug);
                            } else {
                                $bot->log("No {$type} data received!", Log\Logger::WARN);
                            }
                            _destroy($result, $data);
                        }
                    );
                    
                    if ($debug) $bot->debug('Update cities: ' . REDIS_NAMESPACE . $user_key);
                    _destroy($json, $data);
                    
                    return array(200, Json\Json::Encode(array('result'=>'success')));
                    break;
                
                case '/post/cities':
                
                    $type       = "PLAYER_{$user_id}|CITIES";
                    $user_key   = "user:{$user_id}";
                    $session_id = $json->{'s'};
                    $user_agent = $json->{'a'};
                    $data       = $json->{'c'};

                    if (!empty($data)) {
                        if ($debug) $bot->log("Got {$type} data!", Log\Logger::DEBUG);
                        $redis->HMSET("{$user_key}:data", array(
                            'collectCities' => (string) $bot->get_timestamp()
                        ));
                        $bot->analyser->using($data, $type, $debug);
                    } else {
                        $bot->log("No {$type} data received!", Log\Logger::WARN);
                    }
                    
                    if ($debug) $bot->debug('Update cities: ' . REDIS_NAMESPACE . $user_key);
                    _destroy($json, $data);

                    return array(200, Json\Json::Encode(array('result'=>'success')));
                    break;
                
                case '/proxy/troops':
                
                    $type = "PLAYER_{$user_id}|TROOPS";
                    $user_key = "user:{$user_id}";
                    $session_id = $json->{'s'};
                    $user_agent = $json->{'a'};

                    $url = '/overview/trpover.php';
                    $bot->log("Call {$type} with URL:{$url}", Log\Logger::DEBUG);
                    $bot->curl->get($url, [
                        CURLOPT_USERAGENT => $user_agent,
                        CURLOPT_REFERER => BOT_SERVER . '/overview/overview.php',
                        CURLOPT_COOKIE => 'sec_session_id=' . $session_id,
                        CURLOPT_COOKIESESSION => true,
                        CURLOPT_HTTPHEADER => array (
                            "Accept: */*",
                            "Host: " . parse_url(BOT_SERVER, PHP_URL_HOST),
                            "Origin: " . BOT_SERVER, 
                            "Accept-Language: de-DE,de;q=0.8,en-US;q=0.6,en;q=0.4",
                            "Cache-Control: no-cache", 
                            "Pragma: no-cache",
                            "X-Requested-With: XMLHttpRequest",
                            "pp-ss: 0",
                        )
                        ], ['id' => $user_id])->then(function (\KHR\React\Curl\Result $result) use($bot, $redis, $type, $user_key, $debug) {
                            $data = $result->getBody();
                            
                            if (!empty($data)) {
                                if ($debug) $bot->log("Got {$type} data!", Log\Logger::DEBUG);
                                $redis->HMSET("{$user_key}:data", array(
                                    'collectTroops' => (string) $bot->get_timestamp()
                                ));
                                $bot->analyser->using($data, $type, $debug);
                            } else {
                                $bot->log("No {$type} data received!", Log\Logger::WARN);
                            }
                            _destroy($result, $data);
                        }
                    );
                    
                    if ($debug) $bot->debug('Update troops: ' . REDIS_NAMESPACE . $user_key);
                    _destroy($json, $data);
                    
                    return array(200, Json\Json::Encode(array('result'=>'success')));
                    break;
                
                case '/post/troops':
                
                    $type       = "PLAYER_{$user_id}|TROOPS";
                    $user_key   = "user:{$user_id}";
                    $session_id = $json->{'s'};
                    $user_agent = $json->{'a'};
                    $data       = $json->{'c'};

                    if (!empty($data)) {
                        if ($debug) $bot->log("Got {$type} data!", Log\Logger::DEBUG);
                        $redis->HMSET("{$user_key}:data", array(
                            'collectTroops' => (string) $bot->get_timestamp()
                        ));
                        $bot->analyser->using($data, $type, $debug);
                    } else {
                        $bot->log("No {$type} data received!", Log\Logger::WARN);
                    }
                    
                    if ($debug) $bot->debug('Update troops: ' . REDIS_NAMESPACE . $user_key);
                    _destroy($json, $data);

                    return array(200, Json\Json::Encode(array('result'=>'success')));
                    break;
                    
                case '/options/offline_data':
                
                    $user_key = "user:{$user_id}";
                    
                    if ($debug) $bot->debug('Redis set offline-data: ' . REDIS_NAMESPACE . $user_key);
                    
                    $result = $redis->HMSET("{$user_key}:offline-data", array(
                        'session_id'    => (string) $json->{'s'},
                        'user_agent'    => (string) $json->{'a'},
                        'last_update'   => (string) $bot->get_timestamp(),
                        'is_enabled'    => $redis->hGet("{$user_key}:options", 'allow_offline')
                    ));
                    _destroy($json, $data);

                    if ($result) { 
                        // JSON encode 
                        return array(200, Json\Json::Encode(array('action'=>'set','result'=>'success')));
                    } else {
                        // JSON encode 
                        return array(202, Json\Json::Encode(array('action'=>'set','result'=>'error','message'=>'Data Error!')));
                    }
                    break;
                    
                case '/options/set':
                
                    $user_key = "user:{$user_id}";
                    if ($debug) $bot->debug('Redis set options: ' . REDIS_NAMESPACE . $user_key);
                    
                    $result = $redis->HMSET("{$user_key}:options", (array) $json);
                    _destroy($json, $data);
                    
                    if ($result) { 
                        // JSON encode 
                        return array(200, Json\Json::Encode(array('action'=>'set','result'=>'success')));
                    } else {
                        // JSON encode 
                        return array(202, Json\Json::Encode(array('action'=>'set','result'=>'error','message'=>'Data Error!')));
                    }
                    break;
                    
                case '/options/get':
                
                    $user_key = "user:{$user_id}";
                    if ($debug) $bot->debug('Redis get options: ' . REDIS_NAMESPACE . $user_key);
                    
                    $result = $redis->HMGET("{$user_key}:options", (array) $json);
                    _destroy($json, $data);
                    
                    if ($result) {
                        // JSON encode 
                        return array(200, Json\Json::Encode(array('action'=>'get','result'=>'success','options'=>$result)));
                    } else {
                        // JSON encode 
                        return array(202, Json\Json::Encode(array('action'=>'get','result'=>'error','message'=>'Database Error!')));
                    }
                    break;
                    
                case '/user/stats/get':
                case '/user/military/get':
                
                    $user_key = "user:{$user_id}";
                    $rank = $redis->hGet("{$user_key}:data", 'alliance_rank_id');
                    $which = ($path === '/user/stats/get') ? 'stats' : 'military';
                    
                    $result = array();
                    $data_count = 0;
                    
                    if ($rank <= \ROLES::MEMBER) {
                        $user      = $json->{'user'};
                        $uid       = $bot->get_user_id($user);
                        $from      = (property_exists($json, 'from')) ? (($json->{'from'} == 'all') ? '-inf' : $json->{'from'}) : '-inf';
                        $to        = (property_exists($json, 'to')) ? (($json->{'to'} == 'all') ? '+inf' : $json->{'to'}) : '+inf';
                        $continent = (property_exists($json, 'continent') && (filter_var($json->{'continent'}, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 55]]) !== false)) ? $json->{'continent'} : false;
                        
                        $user_key = "user:{$uid}";
                        $continent_key = ($continent) ? ":continent:{$continent}" : '';
                        if ($debug) $bot->debug("Redis get {$which}: {$user_key}{$continent_key}:{$which} {$from}, {$to}");
                        
                        if ($which === 'stats') {
                            $_stats = $redis->ZRANGEBYSCORE("{$user_key}{$continent_key}:{$which}", $from, $to, array('withscores' => TRUE));

                            if (!empty($_stats) && is_array($_stats)) {
                                //alliance_id|city_count|points|rank
                                while ( list($val, $key) = each($_stats) ) {
                                    $val = explode('|', $val);
                                    if ($last_ally_id != $val[0]) {
                                        $last_ally_id = $val[0];
                                        $last_ally_name = $bot->get_alliance_name_by_id($val[0]);
                                    }
                                    
                                    $result[$key] = array(
                                        'timestamp'     => $key,
                                        'date'          => date('d.m.Y H:i', $key),
                                        'alliance_name' => $last_ally_name,
                                        'alliance'      => $last_ally_id,
                                        'cities'        => $val[1],
                                        'points'        => $val[2],
                                        'rank'          => $val[3]
                                    );
                                    $data_count++;
                                }
                            }
                        } else {
                            // NOOP
                        }
                    } else 
                        // JSON encode 
                        return array(405, 'Method Not Allowed');
                        
                    _destroy($json, $data, $_stats);

                    if (!empty($result) && $data_count) {
                        // JSON encode 
                        return array(200, Json\Json::Encode(array('action'=>'get','result'=>'success','user'=>$user,'continent'=>$continent,$which=>$result)));
                    } else {
                        // JSON encode 
                        return array(202, Json\Json::Encode(array('action'=>'get','result'=>'error','message'=>'Database Error!')));
                    }
                    break;
                    
                case '/user/data/get':
                    
                    $user_key = "user:{$user_id}";
                    $rank = $redis->hGet("{$user_key}:data", 'alliance_rank_id');
                    
                    $result = false;
                    
                    if ($rank <= \ROLES::MEMBER) {
                        $user      = $json->{'user'};
                        $uid       = $bot->get_user_id($user);
                        $continent = (property_exists($json, 'continent') && (filter_var($json->{'continent'}, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 55]]) !== false)) ? $json->{'continent'} : false;
                        
                        $user_key = "user:{$uid}";
                        $continent_key = ($continent) ? ":continent:{$continent}" : '';
                        if ($debug) $bot->debug('Redis get data: ' . REDIS_NAMESPACE . $user_key . $continent_key);
                        
                        $result = $redis->hGetAll("{$user_key}{$continent_key}:data");
                    } else 
                        // JSON encode 
                        return array(405, 'Method Not Allowed');
                        
                    _destroy($json, $data);
                    
                    if ($result) {
                        $result['alliance_name'] = $bot->get_alliance_name_by_id($result['alliance']);
                        
                        // JSON encode 
                        return array(200, Json\Json::Encode(array('action'=>'get','result'=>'success','user'=>$user,'continent'=>$continent,'data'=>$result)));
                    } else {
                        // JSON encode 
                        return array(202, Json\Json::Encode(array('action'=>'get','result'=>'error','message'=>'Database Error!')));
                    }
                    break;
                    
                case '/alliance/stats/get':
                case '/alliance/military/get':
                    
                    $user_key = "user:{$user_id}";
                    $rank = $redis->hGet("{$user_key}:data", 'alliance_rank_id');
                    $which = ($path === '/alliance/stats/get') ? 'stats' : 'military';
                    
                    $result = array();
                    $data_count = 0;
                    
                    if ($rank <= \ROLES::MEMBER || $bot->is_owner($bot->get_user_name_by_id($user_id))) { 
                        $alliance  = $json->{'alliance'};
                        $aid       = $bot->get_alliance_id($alliance);
                        $from      = (property_exists($json, 'from')) ? (($json->{'from'} == 'all') ? '-inf' : $json->{'from'}) : '-inf';
                        $to        = (property_exists($json, 'to')) ? (($json->{'to'} == 'all') ? '+inf' : $json->{'to'}) : '+inf';
                        $continent = (property_exists($json, 'continent') && (filter_var($json->{'continent'}, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 55]]) !== false)) ? $json->{'continent'} : false;
                        
                        $alliance_key = "alliance:{$aid}";
                        $continent_key = ($continent) ? ":continent:{$continent}" : '';
                        if ($debug) $bot->debug("Redis get {$which}: {$alliance_key}{$continent_key}:{$which} {$from}, {$to}");
                        
                        if ($which === 'stats') {
                            $_stats = $redis->ZRANGEBYSCORE("{$alliance_key}{$continent_key}:stats", $from, $to, array('withscores' => TRUE));

                            if (!empty($_stats) && is_array($_stats)) {
                                //city_count|member_count|points|rank
                                while ( list($val, $key) = each($_stats) ) {
                                    $val = explode('|', $val);
                                    
                                    $result[$key] = array(
                                        'timestamp'     => $key,
                                        'date'          => date('d.m.Y H:i', $key),
                                        'cities'        => $val[0],
                                        'members'       => $val[1],
                                        'points'        => $val[2],
                                        'rank'          => $val[3]
                                    );
                                    $data_count++;
                                }
                            }
                        } else {
                            $_military = $redis->ZRANGEBYSCORE("{$alliance_key}{$continent_key}:military", $from, $to, array('withscores' => TRUE));

                            if (!empty($_military) && is_array($_military)) {
                                //points|rank
                                while ( list($val, $key) = each($_military) ) {
                                    $val = explode('|', $val);
                                    
                                    $result[$key] = array(
                                        'timestamp'     => $key,
                                        'date'          => date('d.m.Y H:i', $key),
                                        'points'        => $val[0],
                                        'rank'          => $val[1]
                                    );
                                    $data_count++;
                                }
                            }
                        }
                    } else 
                        // JSON encode 
                        return array(405, 'Method Not Allowed');
                        
                    _destroy($json, $data, $_stats);
                    
                    if (!empty($result) && $data_count) {
                        // JSON encode 
                        return array(200, Json\Json::Encode(array('action'=>'get','result'=>'success','alliance'=>$alliance,'continent'=>$continent,$which=>$result)));
                    } else {
                        // JSON encode 
                        return array(202, Json\Json::Encode(array('action'=>'get','result'=>'error','message'=>'Database Error!')));
                    }
                    break;
                    
                case '/alliance/data/get':
                    
                    $user_key = "user:{$user_id}";
                    $rank = $redis->hGet("{$user_key}:data", 'alliance_rank_id');
                    
                    $result = false;
                    
                    if ($rank <= \ROLES::MEMBER) {
                        $alliance  = $json->{'alliance'};
                        $aid       = $bot->get_alliance_id($alliance);
                        $continent = (property_exists($json, 'continent') && (filter_var($json->{'continent'}, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 55]]) !== false)) ? $json->{'continent'} : false;
                        
                        $alliance_key = "alliance:{$aid}";
                        $continent_key = ($continent) ? ":continent:{$continent}" : '';
                        if ($debug) $bot->debug('Redis get data: ' . REDIS_NAMESPACE . $alliance_key . $continent_key);
                        
                        $result = $redis->hGetAll("{$alliance_key}{$continent_key}:data");
                    } else 
                        // JSON encode 
                        return array(405, 'Method Not Allowed');
                        
                    _destroy($json, $data);
                    
                    if ($result) {                       
                        // JSON encode 
                        return array(200, Json\Json::Encode(array('action'=>'get','result'=>'success','alliance'=>$alliance,'continent'=>$continent,'data'=>$result)));
                    } else {
                        // JSON encode 
                        return array(202, Json\Json::Encode(array('action'=>'get','result'=>'error','message'=>'Database Error!')));
                    }
                    break;
                
                case '/alliance/members/get':
                    
                    $user_key = "user:{$user_id}";
                    $rank = $redis->hGet("{$user_key}:data", 'alliance_rank_id');
                    
                    $result = false;
                    
                    if ($rank <= \ROLES::MEMBER) {
                        $alliance  = $json->{'alliance'};
                        $aid       = $bot->get_alliance_id($alliance);
                        $continent = (property_exists($json, 'continent') && (filter_var($json->{'continent'}, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 55]]) !== false)) ? $json->{'continent'} : false;
                        
                        $alliance_key = "alliance:{$aid}";
                        $continent_key = ($continent) ? ":continent:{$continent}" : '';
                        if ($debug) $bot->debug('Redis get members: ' . REDIS_NAMESPACE . $alliance_key . $continent_key);
                        
                        $result = $redis->hGetAll("{$alliance_key}{$continent_key}:members");
                    } else 
                        // JSON encode 
                        return array(405, 'Method Not Allowed');
                        
                    _destroy($json, $data);
                    
                    if ($result) {                       
                        // JSON encode 
                        return array(200, Json\Json::Encode(array('action'=>'get','result'=>'success','alliance'=>$alliance,'continent'=>$continent,'members'=>$result)));
                    } else {
                        // JSON encode 
                        return array(202, Json\Json::Encode(array('action'=>'get','result'=>'error','message'=>'Database Error!')));
                    }
                    break;
                
                case '/continent/data/get':
                    
                    $user_key = "user:{$user_id}";
                    $rank = $redis->hGet("{$user_key}:data", 'alliance_rank_id');
                    
                    $result = false;
                    
                    if ($rank <= \ROLES::MEMBER) {
                        $continent  = $json->{'continent'};
                        $continent_key = "continent:{$continent}";
                        if ($debug) $bot->debug('Redis get data: ' . REDIS_NAMESPACE . $continent_key);
                        
                        $result = $redis->hGetAll("{$continent_key}:data");
                    } else 
                        // JSON encode 
                        return array(405, 'Method Not Allowed');
                        
                    _destroy($json, $data);
                    
                    if ($result) {                       
                        // JSON encode 
                        return array(200, Json\Json::Encode(array('action'=>'get','result'=>'success','continent'=>$continent,'data'=>$result)));
                    } else {
                        // JSON encode 
                        return array(202, Json\Json::Encode(array('action'=>'get','result'=>'error','message'=>'Database Error!')));
                    }
                    break;
                
                case '/continent/stats/get':
                    
                    $user_key = "user:{$user_id}";
                    $rank = $redis->hGet("{$user_key}:data", 'alliance_rank_id');
                    $which = ($path === '/continent/stats/get') ? 'stats' : 'military';
                    
                    $result = array();
                    $data_count = 0;
                    
                    if ($rank <= \ROLES::MEMBER || $bot->is_owner($bot->get_user_name_by_id($user_id))) { 
                        $continent  = $json->{'continent'};
                        $from      = (property_exists($json, 'from')) ? (($json->{'from'} == 'all') ? '-inf' : $json->{'from'}) : '-inf';
                        $to        = (property_exists($json, 'to')) ? (($json->{'to'} == 'all') ? '+inf' : $json->{'to'}) : '+inf';
                        
                        $continent_key = "continent:{$continent}";
                        if ($debug) $bot->debug("Redis get {$which}: {$continent_key}:{$which} {$from}, {$to}");
                        
                        if ($which === 'stats') {
                            $_stats = $redis->ZRANGEBYSCORE("{$continent_key}:stats", $from, $to, array('withscores' => TRUE));

                            if (!empty($_stats) && is_array($_stats)) {
                                //free_count|settled_count|cities_count|castle_count
                                while ( list($val, $key) = each($_stats) ) {
                                    $val = explode('|', $val);
                                    
                                    $result[$key] = array(
                                        'timestamp'     => $key,
                                        'date'          => date('d.m.Y H:i', $key),
                                        'free_count'        => $val[0],
                                        'settled_count'     => $val[1],
                                        'cities_count'      => $val[2],
                                        'castle_count'      => $val[3]
                                    );
                                    $data_count++;
                                }
                            }
                        } else {
                            // NOOP
                        }
                    } else 
                        // JSON encode 
                        return array(405, 'Method Not Allowed');
                        
                    _destroy($json, $data, $_stats);
                    
                    if (!empty($result) && $data_count) {
                        // JSON encode 
                        return array(200, Json\Json::Encode(array('action'=>'get','result'=>'success','continent'=>$continent,$which=>$result)));
                    } else {
                        // JSON encode 
                        return array(202, Json\Json::Encode(array('action'=>'get','result'=>'error','message'=>'Database Error!')));
                    }
                    break;
                
                case '/downloads/get':
                    $result = false;
                    $user_key = "user:{$user_id}";
                    $alliance_key = "alliance:{$bot->ally_id}";
                    
                    if ($debug) $bot->debug('Redis get download: ' . REDIS_NAMESPACE . $user_key);
                    
                    $extension = $json->{'extension'};
                    
                    $rank = $redis->hGet("{$user_key}:data", 'alliance_rank_id');
                    $bot->log("Data {$extension} request from {$user_id}/{$rank}!", Log\Logger::INFO);
                    if ($rank) {
                        switch ($extension) {
                            case 'show_troops':
                                if ($rank <= \ROLES::OFFICER) {
                                    $result = [];
                                    $continent = $json->{'continent'};
                                    $file_name = (($continent == 'all') ? 'All_Offence_Troops.csv' : $continent . '_Offence_Troops.csv');
                                    array_push($result, array(
                                        'Continent',
                                        'Coords',
                                        'Name',
                                        'Type',
                                        'Member',
                                        'Note',
                                        'Wall_lvl',
                                        'Spot_time',
                                        'Academy',
                                        'Senator_total',
                                        'Warship_total',
                                        'Galley_total',
                                        'Scorpion_total',
                                        'Ram_total',
                                        'Druid_total',
                                        'Horseman_total',
                                        'Sorcerer_total',
                                        'Vanquisher_total',
                                        'Total_ts',
                                        'Last_update'
                                    ));
                                
                                    $members = $redis->sMembers("{$alliance_key}:members");
                                    
                                    $troops = array(
                                    // offence
                                    'Senator_total'     => array('off', 1,      'Senator'),
                                    'Warship_total'     => array('off', 100,    'Warship'),
                                    'Galley_total'      => array('off', 100,    'Galley'),
                                    'Scorpion_total'    => array('off', 10,     'Scorpion'),
                                    'Ram_total'         => array('off', 10,     'Ram'),
                                    'Druid_total'       => array('off', 2,      'Druid'),
                                    'Horseman_total'    => array('off', 2,      'Horseman'),
                                    'Sorcerer_total'    => array('off', 1,      'Sorcerer'),
                                    'Vanquisher_total'  => array('off', 1,      'Sorcerer'),
                                    // defence
                                    'Stinger_total'     => array('def', 100,    'Stinger'),
                                    'Praetor_total'     => array('def', 2,      'Praetor'),
                                    'Arbalist_total'    => array('def', 2,      'Arbalist'),
                                    'Triari_total'      => array('def', 1,      'Triari'),
                                    'Ranger_total'      => array('def', 1,      'Ranger'),
                                    'Ballista_total'    => array('def', 10,     'Ballista'),
                                    'Guard_total'       => array('def', 1,      'Guard'),
                                    'Priestess_total'   => array('def', 1,      'Priestess'),
                                    'Scout_total'       => array('def', 2,      'Scout')
                                    );
                                    
                                    if ($continent == 'all') $data_keys = $redis->getKeys("city:*:*:data");
                                    else $data_keys = $redis->getKeys("city:{$continent[1]}*:{$continent[0]}*:data");
                                    
                                    if (!empty($data_keys)) {
                                        foreach($data_keys as $data_key) {
                                            $data_city = $redis->hGetAll($data_key);
                                            
                                            if (!is_array($data_city) || !in_array($data_city['player'], $members)) continue;
                                            
                                            if ($data_city['castle'] != 'Y') continue;
                                            
                                            $troops_city = $redis->hGetAll("city:{$data_city['coords']}:troops");
                                            $info_city = $redis->hGetAll("city:{$data_city['coords']}:info");
                                            
                                            if (!empty($troops_city)) { //  && $data_city['temple'] == 'N'
                                                $troop_ts = 0;
                                                $troop_amount = 0;
                                                foreach($troops as $troop => $type) {
                                                    if ($type[0] == 'def') continue;
                                                    $troop_amount = $troops_city[$troop];
                                                    $troop_ts += $troop_amount * $type[1];
                                                }
                                                array_push($result, array(
                                                $data_city['continent'],
                                                $data_city['coords'],
                                                $data_city['name'],
                                                (($data_city['temple'] == 'Y') ? 'Temple' : (($data_city['water'] == 'Y') ? 'Water Castle' : 'Castle')),
                                                $data_city['player'],
                                                (($info_city) ? $info_city['reference'] : ''),
                                                $troops_city['wall_lvl'],
                                                $troops_city['spot_time'],
                                                ((!empty($info_city)) ? $info_city['Academy'] : (($troops_city['Senator_total'] >= 1) ? 'Y' : 'N')),
                                                $troops_city['Senator_total'],
                                                $troops_city['Warship_total'],
                                                $troops_city['Galley_total'],
                                                $troops_city['Scorpion_total'],
                                                $troops_city['Ram_total'],
                                                $troops_city['Druid_total'],
                                                $troops_city['Horseman_total'],
                                                $troops_city['Sorcerer_total'],
                                                $troops_city['Vanquisher_total'],
                                                $troop_ts,
                                                date("d/m H:i", $troops_city['date'])
                                                ));
                                            }
                                        }
                                    }
                                } else 
                                    // JSON encode 
                                    return array(405, 'Method Not Allowed');
                            break;
                        }
                    } else 
                        // JSON encode 
                        return array(405, 'Method Not Allowed');
                    
                    _destroy($json, $data);
                    if ($result) {
                        $export = new \ExportDataCSV('string');
                        $export->filename = (($file_name) ? $file_name : 'test.csv');
                        $export->initialize();
                        foreach($result as $row) {
                            $export->addRow($row);
                        }
                        $export->finalize();
                        
                        $exportedData =  utf8_decode($export->getString());   
                        $exportedHeaders =  $export->getHttpHeaders();                   
                        _destroy($result, $export);
                        return array(200, $exportedData, $exportedHeaders);
                    } else {
                        // JSON encode 
                        $bot->log("No data send!", Log\Logger::WARN);
                        return array(202, Json\Json::Encode(array('action'=>'get','result'=>'error','message'=>'Database Error!')));
                    }
                    break;
                    
                case '/extensions/get':
                
                    $result = false;
                    $user_key = "user:{$user_id}";
                    $alliance_key = "alliance:{$bot->ally_id}";
                    
                    if ($debug) $bot->debug('Redis get extensions: ' . REDIS_NAMESPACE . $user_key);
                    
                    $rank = $redis->hGet("{$user_key}:data", 'alliance_rank_id');
                    $rights = $bot->get_alliance_rights_by_id($bot->ally_id);
                    
                    $_io_right = (isset($rights['io'])) ? $rights['io'] : \ROLES::OFFICER;
                    $_io1h_right = (isset($rights['io1h'])) ? $rights['io1h'] : \ROLES::OFFICER;
                    
                    if (in_array('show_fun_chat' , $json)) {
                        $result['show_fun_chat']['enabled'] = true;
                    }
                    
                    if (in_array('show_cross_chat' , $json)) {
                        $result['show_cross_chat']['enabled'] = true;
                    }
                        
                    if ($rank) {

                        if (in_array('show_lead_chat' , $json)) {
                            $result['show_lead_chat']['enabled'] = false;
                            if ($rank <= \ROLES::OFFICER) {
                                $result['show_lead_chat']['enabled'] = true;
                                }
                        }
                        
                        if (in_array('show_sister_incomings' , $json)) {
                            $result['show_sister_incomings']['enabled'] = false;
                            if (defined('BOT_SISTER_ID') && $rank <= $_io_right || $rank <= $_io1h_right) {
                                $result['show_sister_incomings']['enabled'] = true;
                            }
                        }
                        
                        if (in_array('show_sister_outgoings' , $json)) {
                            $result['show_sister_outgoings']['enabled'] = false;
                            if (defined('BOT_SISTER_ID') && $rank <= $_io_right || $rank <= $_io1h_right) {
                                $result['show_sister_outgoings']['enabled'] = true;
                            }
                        }
                        
                        if (in_array('show_substitutes' , $json)) {
                            $result['show_substitutes']['data'] = [];
                            $result['show_substitutes']['enabled'] = false;
                            if ($rank <= \ROLES::OFFICER) {
                                $result['show_substitutes']['enabled'] = true;
                                $members = $redis->sMembers("{$alliance_key}:members");
                                if (is_array($members)) foreach ($members as $member) {
                                    $uid = $bot->get_user_id($member);
                                    $result['show_substitutes']['data'][$member] = $redis->hMGet("user:{$uid}:data", ['substitute', 'getSubstitute']);
                                    $result['show_substitutes']['data'][$member]['allow_substitute'] = $redis->hGet("{$user_key}:options", 'allow_substitute');
                                }
                            }
                        }
                        
                        if (in_array('show_scriptusers' , $json)) {
                            $result['show_scriptusers']['data'] = [];
                            $result['show_scriptusers']['enabled'] = false;
                            if ($rank <= \ROLES::OFFICER) {
                                $result['show_scriptusers']['enabled'] = true;
                                $members = $redis->sMembers("{$alliance_key}:members");
                                if (is_array($members)) foreach ($members as $member) {
                                    $uid = $bot->get_user_id($member);                  
                                    $result['show_scriptusers']['data'][$member] = $redis->hMGet("user:{$uid}:api", ['last_used', 'version', 'outdated']);
                                    $result['show_scriptusers']['data'][$member]['outdated'] = ($result['show_scriptusers']['data'][$member]['outdated']) ? true : false;
                                }
                            }
                        }
                        
                        if (in_array('show_troops' , $json)) {
                            $result['show_troops']['data'] = array(
                                'continents' => [],
                                'troops'     => []
                            );
                            $result['show_troops']['enabled'] = false;
                            if ($rank <= \ROLES::OFFICER) {
                                $result['show_troops']['enabled'] = true;
                                $members = $redis->sMembers("{$alliance_key}:members");
                                $troops = array(
                                    // offence
                                    'Senator_total'     => array('off', 1,      'Senator'),
                                    'Warship_total'     => array('off', 100,    'Warship'),
                                    'Galley_total'      => array('off', 100,    'Galley'),
                                    'Scorpion_total'    => array('off', 10,     'Scorpion'),
                                    'Ram_total'         => array('off', 10,     'Ram'),
                                    'Druid_total'       => array('off', 2,      'Druid'),
                                    'Horseman_total'    => array('off', 2,      'Horseman'),
                                    'Sorcerer_total'    => array('off', 1,      'Sorcerer'),
                                    'Vanquisher_total'  => array('off', 1,      'Sorcerer'),
                                    // defence
                                    'Stinger_total'     => array('def', 100,    'Stinger'),
                                    'Praetor_total'     => array('def', 2,      'Praetor'),
                                    'Arbalist_total'    => array('def', 2,      'Arbalist'),
                                    'Triari_total'      => array('def', 1,      'Triari'),
                                    'Ranger_total'      => array('def', 1,      'Ranger'),
                                    'Ballista_total'    => array('def', 10,     'Ballista'),
                                    'Guard_total'       => array('def', 1,      'Guard'),
                                    'Priestess_total'   => array('def', 1,      'Priestess'),
                                    'Scout_total'       => array('def', 2,      'Scout')
                                );

                                $result['show_troops']['data']['troops'] = array(
                                    'continents'    => [],
                                    'members'       => [],
                                    'temples'       => 0,
                                    'castles'       => 0,
                                    'water_castles' => 0,
                                    'water_castles' => 0,
                                    'total_off'     => 0,
                                    'total_def'     => 0
                                );

                                $continents = [];
                                $data_keys = $redis->getKeys("city:*:*:data");
                                if (!empty($data_keys)) {
                                    foreach($data_keys as $data_key) {
                                        $data_city = $redis->hGetAll($data_key);
                                        
                                        if (!is_array($data_city) || !in_array($data_city['player'], $members)) continue;
                                        
                                        if ($data_city['castle'] != 'Y') continue;
                                        
                                        $uid = $bot->get_user_id($data_city['player']);
                                        $collectTroops = $redis->hGet("user:{$uid}:data", 'collectTroops');
                                        $allowTroops = $redis->hGet("user:{$uid}:options", 'allow_troops');
                                        $continent = $data_city['continent'];
                                        
                                        if (empty($result['show_troops']['data']['troops']['continents'][$continent])) $result['show_troops']['data']['troops']['continents'][$continent] = array(
                                            'members'       => [],
                                            'temples'       => 0,
                                            'castles'       => 0,
                                            'water_castles' => 0,
                                            'water_castles' => 0,
                                            'total_off'     => 0,
                                            'total_def'     => 0
                                        );
                                        if (empty($result['show_troops']['data']['troops']['continents'][$continent]['members'][$data_city['player']])) $result['show_troops']['data']['troops']['continents'][$continent]['members'][$data_city['player']] = array(
                                            'temples'       => 0,
                                            'castles'       => 0,
                                            'water_castles' => 0,
                                            'total_off'     => 0,
                                            'total_def'     => 0,
                                            'allow_troops'  => $allowTroops,
                                            'collectTroops' => ((!is_numeric($collectTroops)) ? strtotime($collectTroops) : $collectTroops)
                                        );
                                        if (empty($result['show_troops']['data']['troops']['members'][$data_city['player']])) $result['show_troops']['data']['troops']['members'][$data_city['player']] = array(
                                            'temples'       => 0,
                                            'castles'       => 0,
                                            'water_castles' => 0,
                                            'total_off'     => 0,
                                            'total_def'     => 0,
                                            'allow_troops'  => $allowTroops,
                                            'collectTroops' => ((!is_numeric($collectTroops)) ? strtotime($collectTroops) : $collectTroops)
                                        );
                                        
                                        if ($data_city['temple'] == 'Y') {
                                            $result['show_troops']['data']['troops']['temples']++;
                                            $result['show_troops']['data']['troops']['continents'][$continent]['temples']++;
                                            $result['show_troops']['data']['troops']['members'][$data_city['player']]['temples']++;
                                            $result['show_troops']['data']['troops']['continents'][$continent]['members'][$data_city['player']]['temples']++;
                                        } else if ($data_city['water'] == 'Y') {
                                            $result['show_troops']['data']['troops']['water_castles']++;
                                            $result['show_troops']['data']['troops']['continents'][$continent]['water_castles']++;
                                            $result['show_troops']['data']['troops']['members'][$data_city['player']]['water_castles']++;
                                            $result['show_troops']['data']['troops']['continents'][$continent]['members'][$data_city['player']]['water_castles']++;
                                        } else {
                                            $result['show_troops']['data']['troops']['castles']++;
                                            $result['show_troops']['data']['troops']['continents'][$continent]['castles']++;
                                            $result['show_troops']['data']['troops']['members'][$data_city['player']]['castles']++;
                                            $result['show_troops']['data']['troops']['continents'][$continent]['members'][$data_city['player']]['castles']++;
                                        }
                                        
                                        $troops_city = $redis->hGetAll("city:{$data_city['coords']}:troops");

                                        if (!empty($troops_city) && $data_city['temple'] == 'N') foreach($troops as $troop => $type) {
                                            if ($type[0] == 'def') continue;
                                            $troop_amount = $troops_city[$troop];
                                            $troop_ts = $troop_amount * $type[1];
                                            $result['show_troops']['data']['troops']["total_{$type[0]}"] += $troop_ts;
                                            $result['show_troops']['data']['troops']['continents'][$continent]["total_{$type[0]}"] += $troop_ts;
                                            $result['show_troops']['data']['troops']['members'][$data_city['player']]["total_{$type[0]}"] += $troop_ts;
                                            $result['show_troops']['data']['troops']['continents'][$continent]['members'][$data_city['player']]["total_{$type[0]}"] += $troop_ts;
                                        }
                                        if (!in_array($continent, $continents)) array_push($continents, $continent);
                                    }
                                    if (!empty($continents)) sort($continents);
                                    $result['show_troops']['data']['continents'] = $continents;
                                }
                            }
                        }
                    }
                    
                    _destroy($json, $data);
                    if ($result) {
                        // JSON encode 
                        _destroy($result, $members);
                        return array(200, Json\Json::Encode(array('action'=>'get','result'=>'success','extensions'=>$result)));
                    } else {
                        // JSON encode 
                        return array(202, Json\Json::Encode(array('action'=>'get','result'=>'error','message'=>'Database Error!')));
                    }
                    break;
                    
                case '/notes/set':
                    $pos = $json->{'pos'};
                    $note = $json->{'note'};

                    _destroy($json, $data);
                    if (Bot\CotG_Bot::is_string_pos($pos)) {
                        $redis->hSet("notes:{$pos}", 'text', $note); 
                        // JSON encode 
                        _destroy($note);
                        return array(200, Json\Json::Encode(array('pos'=>$pos,'result'=>'success')));
                    } else {
                        // JSON encode 
                        _destroy($note); 
                        return array(202, Json\Json::Encode(array('pos'=>$pos,'result'=>'error','message'=>'no pos given!')));
                    }
                    break;
                    
                case '/notes/get':
                    $pos = $json->{'pos'};

                    _destroy($json, $data);
                    if (Bot\CotG_Bot::is_string_pos($pos)) {
                        $note = $redis->hGet("notes:{$pos}", 'text'); 
                        // JSON encode 
                        return array(200, Json\Json::Encode(array('pos'=>$pos,'result'=>'success','note'=>$note)));
                    } else {
                        // JSON encode 
                        return array(202, Json\Json::Encode(array('pos'=>$pos,'result'=>'error','message'=>'no pos given!')));
                    }
                    break;
                
                case '/settlers/set':
                    $pos = $json->{'pos'};
                    $eta = $json->{'eta'};

                    _destroy($json, $data);
                    if (Bot\CotG_Bot::is_string_pos($pos)) {
                        
                        // JSON encode 
                        _destroy($note);
                        return array(200, Json\Json::Encode(array('pos'=>$pos,'result'=>'success')));
                    } else {
                        // JSON encode 
                        _destroy($note); 
                        return array(202, Json\Json::Encode(array('pos'=>$pos,'result'=>'error','message'=>'no pos given!')));
                    }
                    break;
                    
                case '/settlers/get':
                    $pos = $json->{'pos'};

                    _destroy($json, $data);
                    if (Bot\CotG_Bot::is_string_pos($pos)) {
                        $settler_key = "settler";
                        $alliance_key = "alliance:*";
                        $continent = Bot\CotG_Bot::get_continent_by_pos($pos);
                        $continent_key = "continent:{$continent}";
                        $settler_pattern = "{$settler_key}:{$alliance_key}:{$continent_key}:settlers:{$pos}";
                        list($settler_key) = $redis->clearKey($redis->Keys("{$settler_pattern}"), "/{$settler_pattern}/");
                        
                        if ($settler_key) {
                            $settler = $redis->get($settler_key);
                            $settletime = date('d.m.Y H:i:s', time() - (SETTLERTTL - $redis->TTL($settler_key)));
                            $note = array(
                                'settler' => $settler,
                                'settletime' => $settletime
                            );
                        } else {
                            $note = array(
                                'settler' => '',
                                'settletime' => ''
                            );
                        }
                        
                        // JSON encode 
                        return array(200, Json\Json::Encode(array('pos'=>$pos,'result'=>'success','settler'=>$note)));
                    } else {
                        // JSON encode 
                        return array(202, Json\Json::Encode(array('pos'=>$pos,'result'=>'error','message'=>'no or wrong pos given!')));
                    }
                    break;
                    
                case '/incomings/get':
                    $user_key = "user:{$user_id}";
                    $rank = $redis->hGet("{$user_key}:data", 'alliance_rank_id');
                    
                    $result = [];
                    
                    if (defined('BOT_SISTER_ID')) {
                        
                        $sister_ids = explode('|', $bot->sister_id);
                        foreach ($sister_ids as $sister_id) {
                            $rights = $bot->get_alliance_rights_by_id($sister_id);
                            $_io_right = (isset($rights['io'])) ? $rights['io'] : \ROLES::OFFICER;
                            $_io1h_right = (isset($rights['io1h'])) ? $rights['io1h'] : \ROLES::OFFICER;
                        
                            if ($rank <= $_io_right) {
                                $incoming_key = "incoming";
                                $_result = $redis->get("{$incoming_key}:alliance:{$sister_id}:origin");
                            } else if ($rank <= $_io1h_right) {
                                $incoming_key = "incoming1h";
                                $_result = $redis->get("{$incoming_key}:alliance:{$sister_id}:origin");
                            }
                            if ($_result) $result = array_merge($result, Json\Json::Decode($_result));
                        }
                    }
                    _destroy($json, $data, $_stats, $_result);

                    if (!empty($result)) {
                        // JSON encode 
                        return array(200, Json\Json::Encode(array('action'=>'get','result'=>'success','incomings'=>Json\Json::Encode($result))));
                    } else {
                        // JSON encode 
                        return array(202, Json\Json::Encode(array('action'=>'get','result'=>'error','message'=>'Database Error!')));
                    }
                    break;
                    
                case '/outgoings/get':
                    $user_key = "user:{$user_id}";
                    $rank = $redis->hGet("{$user_key}:data", 'alliance_rank_id');
                    
                    $result = [];
                    
                    if (defined('BOT_SISTER_ID')) {
                        
                        $sister_ids = explode('|', $bot->sister_id);
                        foreach ($sister_ids as $sister_id) {
                            $rights = $bot->get_alliance_rights_by_id($sister_id);
                            $_io_right = (isset($rights['io'])) ? $rights['io'] : \ROLES::OFFICER;
                            $_io1h_right = (isset($rights['io1h'])) ? $rights['io1h'] : \ROLES::OFFICER;
                            
                             if ($rank <= $_io_right) {
                                $outgoing_key = "outgoing";
                                $_result = $redis->get("{$outgoing_key}:alliance:{$sister_id}:origin");
                            } else if ($rank <= $_io1h_right) {
                                $outgoing_key = "outgoing1h";
                                $_result = $redis->get("{$outgoing_key}:alliance:{$sister_id}:origin");
                            }
                            if ($_result) $result = array_merge($result, Json\Json::Decode($_result));
                        }

                    }
                    _destroy($json, $data, $_stats, $_result);

                    if (!empty($result)) {
                        // JSON encode 
                        return array(200, Json\Json::Encode(array('action'=>'get','result'=>'success','outgoings'=>Json\Json::Encode($result))));
                    } else {
                        // JSON encode 
                        return array(202, Json\Json::Encode(array('action'=>'get','result'=>'error','message'=>'Database Error!')));
                    }
                    break;
                    
                default:
                    _destroy($json, $data);
                    if ($debug) $this->debug("API not implemented!");
                    return array(501, 'Not Implemented');
            }
        }
    }        