<?php
    global $bot;

    use Zend\Log\LoggerAwareInterface;
    use Zend\Log\LoggerInterface;
    use CotG\Bot\Observer;
    use CotG\Bot\Json;
    use CotG\Bot\Log;
    use CotG\Bot\Data;
    use CotG\Bot\Cron;
    use CotG\Bot;
    
    $bot->add_category('data', array(), PUBLICY);
    
    // crons
    
    $bot->add_cron_event(Cron\CronDaemon::HOURLY,   // Cron key
    "AllianceOfflineUpdate",                        // command key
    "LouBot_offline_user_update_cron",              // callback function
    function ($bot, $data) {
        global $redis;
        //to disable return;
        return;
        if (!$redis->status()) return;
        $redis->SADD("stats:AllianceOfflineUpdate", (string)time());
        
        $offline_keys = $redis->getKeys("user:*:offline-data");
        if (is_array($offline_keys)) foreach($offline_keys as $offline_key) {
            preg_match('/user:(\d*):offline-data/i', $offline_key, $match);
            $uid = $match[1];
            $debug = $bot->isDebug();
            $user_key = "user:{$uid}";
            $offline_data = $redis->hGetAll($offline_key);
            // call offline data if last update older than 1 hour and 5 minutes
            if ($offline_data['is_enabled'] && (($offline_data['last_update'] + 3900) < time() || $debug)) {
                if ($debug) $bot->log("Got offline data for {$uid}!", Log\Logger::DEBUG);
                $offline_options = $redis->hGetAll("{$user_key}:options");
                if (is_array($offline_options)) {
                    if ($offline_options['allow_reinforcements']) {
                        $type = "PLAYER_{$uid}|REINFORCEMENTS";
                        
                        $session_id = $offline_data['session_id'];
                        $user_agent = $offline_data['user_agent'];

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
                            ], ['id' => $uid])->then(function (\KHR\React\Curl\Result $result) use($bot, $redis, $type, $user_key, $debug) {
                                $data = $result->getBody();
                                
                                if (!empty($data)) {
                                    if ($debug) $bot->log("Got offline {$type} data!", Log\Logger::DEBUG);
                                    $redis->HMSET("{$user_key}:data", array(
                                        'collectReinforcements' => (string)time()
                                    ));
                                    $bot->analyser->using($data, $type, $debug);
                                } else {
                                    $bot->log("No offline {$type} data received!", Log\Logger::WARN);
                                }
                                _destroy($result, $data);
                            }
                        );
                        
                        if ($debug) $bot->debug('Update reinforcements: ' . REDIS_NAMESPACE . $user_key);
                    }
                    if ($offline_options['allow_substitute']) {
                        $type = "PLAYER_{$uid}|SUBSTITUTE";
                        
                        $session_id = $offline_data['session_id'];
                        $user_agent = $offline_data['user_agent'];

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
                            ], ['id' => $uid])->then(function (\KHR\React\Curl\Result $result) use($bot, $redis, $type, $user_key, $debug) {
                                $data = $result->getBody();
                                
                                if (!empty($data)) {
                                    if ($debug) $bot->log("Got offline {$type} data!", Log\Logger::DEBUG);
                                    $redis->HMSET("{$user_key}:data", array(
                                        'getSubstitute' => (string)time()
                                    ));
                                    $bot->analyser->using($data, $type, $debug);
                                } else {
                                    $bot->log("No offline {$type} data received!", Log\Logger::WARN);
                                }
                                _destroy($result, $data);
                            }
                        );
                        
                        if ($debug) $bot->debug('Update substitute: ' . REDIS_NAMESPACE . $user_key);
                    }
                    if ($offline_options['allow_troops']) {
                        $type = "PLAYER_{$uid}|TROOPS";
                        
                        $session_id = $offline_data['session_id'];
                        $user_agent = $offline_data['user_agent'];
                        
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
                            ], ['id' => $uid])->then(function (\KHR\React\Curl\Result $result) use($bot, $redis, $type, $user_key, $debug) {
                                $data = $result->getBody();
                                
                                if (!empty($data)) {
                                    if ($debug) $bot->log("Got offline {$type} data!", Log\Logger::DEBUG);
                                    $redis->HMSET("{$user_key}:data", array(
                                        'collectTroops' => (string)time()
                                    ));
                                    $bot->analyser->using($data, $type, $debug);
                                } else {
                                    $bot->log("No offline {$type} data received!", Log\Logger::WARN);
                                }
                                _destroy($result, $data);
                            }
                        );
                        
                        if ($debug) $bot->debug('Update troops: ' . REDIS_NAMESPACE . $user_key);
                    }
                    if ($offline_options['allow_cities']) {
                        $type = "PLAYER_{$uid}|CITIES";
                        
                        $session_id = $offline_data['session_id'];
                        $user_agent = $offline_data['user_agent'];
                        
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
                            ], ['id' => $uid])->then(function (\KHR\React\Curl\Result $result) use($bot, $redis, $type, $user_key, $debug) {
                                $data = $result->getBody();
                                
                                if (!empty($data)) {
                                    if ($debug) $bot->log("Got offline {$type} data!", Log\Logger::DEBUG);
                                    $redis->HMSET("{$user_key}:data", array(
                                        'collectCities' => (string)time()
                                    ));
                                    $bot->analyser->using($data, $type, $debug);
                                } else {
                                    $bot->log("No offline {$type} data received!", Log\Logger::WARN);
                                }
                                _destroy($result, $data);
                            }
                        );
                        
                        if ($debug) $bot->debug('Update cities: ' . REDIS_NAMESPACE . $user_key);
                    }
                }
            }
        }
        _destroy($data);
    }, 'data');
    
    // callbacks
    
    $bot->add_data_hook("CityTroops",               // command key
    "LouBot_city_troops_update",                    // callback function
    function ($bot, $stat) {
        global $redis;
        if (empty($stat['id'])||$stat['id'] != USER . '|' . TROOPS||!$redis->status()) return;
        
        $pos = $stat['data']['coords'];
        $str_time = (string)time();

        if (Bot\CotG_Bot::is_string_pos($pos)) {
            $stat['data']['user'] = $redis->HGET("user:{$stat['user']}:data", 'name');
            $redis->hMSet("city:{$pos}:troops", $stat['data']);
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
            foreach($troops as $troop => $type) {
                $troop_amount = $stat['data'][$troop];
                $troop_ts[$type[0]] += $troop_amount * $type[1];
            }
            // generate stat key: spot_time|wall_lvl|total_troops|total_off|total_def
            $stats = sprintf('%s|%d|%d|%d|%d', $stat['data']['spot_time'], $stat['data']['wall_lvl'], $stat['data']['total_troops'], $troop_ts['off'], $troop_ts['def']);

            $last = $redis->zRange("city:{$pos}:troops:stats", '-1',  '-1', true);
            if ($last && ($last[0] != $stats || date('Ymd') !== date('Ymd', strtotime($last[1]))) || !$last)
                $zadd = $redis->zAdd("city:{$pos}:troops:stats", $str_time, $stats);
        } 
        _destroy($stat, $troops, $troop_ts, $troop_amount);
    }, 'data');
    
    $bot->add_data_hook("CityInfo",                 // command key
    "LouBot_city_info_update",                      // callback function
    function ($bot, $stat) {
        global $redis;
        if (empty($stat['id'])||$stat['id'] != USER . '|' . CITY||!$redis->status()) return;
        
        $pos = $stat['data']['coords'];
        
        if (Bot\CotG_Bot::is_string_pos($pos)) {
            $stat['data']['user'] = $redis->HGET("user:{$stat['user']}:data", 'name');
            $redis->hMSet("city:{$pos}:info", $stat['data']);
        } 
        _destroy($stat);
    }, 'data');
    
    $bot->add_data_hook("PlayerDataUpdate",           // command key
    "LouBot_player_data_update",                      // callback function
    function ($bot, $stat) {
        global $redis;
        if (empty($stat['id'])||$stat['id'] != PLAYER||!$redis->status()) return;
        $str_time = (string)time();
        $stat['data']['id'] = $bot->get_user_id($stat['data']['name']);
        $stat['data']['alliance_id'] = $bot->get_alliance_id($stat['data']['alliance']);
        if (is_array($stat['data']) && $stat['data']['id'] > 0 && $stat['data']['id'] == $stat['player']) {
            // update player
            $user_key = "user:{$stat['data']['id']}";
            $alliance_key = "alliance:{$stat['data']['alliance_id']}";
            $bot->debug('Redis update: ' . REDIS_NAMESPACE . $user_key);
            
            $redis->HMSET("{$user_key}:data", array(
            'uid'        => $stat['data']['uid']
            ));
            
            // cities            
            $redis->RENAME("{$user_key}:cities","{$user_key}:_cities");
            
            if (is_array($stat['data']['cities'])) foreach ($stat['data']['cities'] as $city => $city_data) {
                $city_data['player'] = $stat['data']['name'];
                $sadd = $redis->SADD("{$user_key}:cities", $city);
                $redis->HMSET("city:{$city}:data", $city_data);
            }
            
            $diff_old = $redis->SDIFF("{$user_key}:_cities","{$user_key}:cities");
            if (is_array($diff_old)) foreach($diff_old as $old) {
                // do something with $old cities
            }
            
            $diff_new = $redis->SDIFF("{$user_key}:cities","{$user_key}:cities");
            if (is_array($diff_new)) foreach($diff_new as $new) {
                // do something with $new cities
            }
            $redis->DEL("{$user_key}:_cities");
        } else if($stat['player'] > 0) {
            // delete player
            $user_key = "user:{$stat['player']}";
            $bot->debug('Redis delete: ' . REDIS_NAMESPACE . $user_key);
            $delete_user_keys = $redis->getKeys("{$user_key}:*");
            if (!empty($delete_user_keys)) foreach($delete_user_keys as $delete_user_key) {
                $redis->DEL("{$delete_user_key}");
            }
        }
        _destroy($stat);
    }, 'data');
    
    $bot->add_data_hook("UserSubstitute",           // command key
    "LouBot_user_substitute_update",                // callback function
    function ($bot, $stat) {
        global $redis;
        if (empty($stat['id'])||$stat['id'] != USER . '|' . SUBSTITUTE||!$redis->status()) return;
        
        $user_key = "user:{$stat['user']}";
        $redis->HMSET("{$user_key}:data", array(
            'substitute' => $stat['data']['substitute']
        ));
        
        $stat['data']['user'] = $redis->HGET("user:{$stat['user']}:data", 'name');
        
        if (!empty($stat['data']['substitutes'])) foreach($stat['data']['substitutes'] as $user) {
            $uid = $redis->hGet('aliase', mb_strtoupper($user));
            $user_key = "user:{$uid}";
            $redis->HMSET("{$user_key}:data", array(
                'substitute' => $stat['data']['user']
            ));
        }
        _destroy($stat);
    }, 'data');