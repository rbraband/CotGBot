<?php
    global $bot;
    $bot->add_category('alliance', array(), ALLIANCE);
    
    use Zend\Log\LoggerAwareInterface;
    use Zend\Log\LoggerInterface;
    use CotG\Bot\Observer;
    use CotG\Bot\Json;
    use CotG\Bot\Log;
    use CotG\Bot\Data;
    use CotG\Bot\Cron;
    use CotG\Bot;
    
    //to disable return;
    #return;
    
    $bot->add_category('inandout', array(), PUBLICY);
    
    // crons / ticks
    
    $bot->add_cron_event(Cron\CronDaemon::HOURLY,   // Cron key
    "AllianceCitiesUpdate",                         // command key
    "LouBot_all_alliance_cities_update_cron",       // callback function
    function ($bot, $data) {
        global $redis;
        //to disable return;
        #return;
        if (!$redis->status()) return;
        $redis->SADD("stats:AllianceCitiesUpdate", (string)time());
        $type = "ALLIANCE|CITIES";
        $debug = $bot->isDebug();

        $_cookie_array = array();
        $_dbCookies = $bot->get_cookies();
        foreach ($_dbCookies as $_name => $_value) {
            $_cookie_array[] = $_name . '=' . $_value;
        }
        
        $url = '/overview/allymemover.php';
        $bot->log("Call {$type} with URL:{$url}", Log\Logger::DEBUG);
        $bot->curl->get($url, [
            CURLOPT_COOKIE => implode('; ', $_cookie_array),
            CURLOPT_REFERER => BOT_SERVER . '/overview/overview.php',
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
            ], ['id' => $type])->then(function (\KHR\React\Curl\Result $result) use($bot, $type, $debug) {
                $data = $result->getBody();
                
                if (!empty($data)) {
                    if ($debug) $bot->log("Got {$type} data!", Log\Logger::DEBUG);

                    $bot->analyser->using($data, $type, $debug);
                } else {
                    $bot->log("No {$type} data received!", Log\Logger::WARN);
                }
                _destroy($result, $data);
            }
        );
        _destroy($data);
    }, 'alliance');
    
    $bot->add_tick_event(Cron\CronDaemon::TICK5,    // Cron key
    "AllianceMemberUpdate",                         // command key
    "LouBot_all_alliance_member_update_cron",       // callback function
    function ($bot, $data) {
        global $redis;
        //to disable return;
        #return;
        if (!$redis->status()) return;
        $redis->SADD("stats:AllianceMemberUpdate", (string)time());
        $type = "ALLIANCE|MEMBERS";
        $debug = $bot->isDebug();
        
        $_cookie_array = array();
        $_dbCookies = $bot->get_cookies();
        foreach ($_dbCookies as $_name => $_value) {
            $_cookie_array[] = $_name . '=' . $_value;
        }
        
        $url = '/overview/allyover.php';
        $bot->log("Call {$type} with URL:{$url}", Log\Logger::DEBUG);
        $bot->curl->get($url, [
            CURLOPT_COOKIE => implode('; ', $_cookie_array),
            CURLOPT_REFERER => BOT_SERVER . '/overview/overview.php',
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
            ], ['id' => $type])->then(function (\KHR\React\Curl\Result $result) use($bot, $type, $debug) {
                $data = $result->getBody();
                
                if (!empty($data)) {
                    if ($debug) $bot->log("Got {$type} data!", Log\Logger::DEBUG);
                    
                    $bot->analyser->using($data, $type, $debug);
                } else {
                    $bot->log("No {$type} data received!", Log\Logger::WARN);
                }
                _destroy($result, $data);
            }
        );
        _destroy($data);
    }, 'alliance');
    
    $bot->add_tick_event(Cron\CronDaemon::TICK1,    // Cron key
    "GetIOUpdate",                                  // command key
    "LouBot_get_io_update_cron",                    // callback function
    function ($bot, $data) {
        global $redis;
        //to disable return;
        //return;
        if (!$redis->status()) return;

        $bot->call('includes/getIO.php', array(), 'ALLIANCE|INANDOUT'); 
        _destroy($data);
    }, 'inandout');
    
    $bot->add_cron_event(Cron\CronDaemon::HOURLY,   // Cron key
    "GetMemberUpdate",                              // command key
    "LouBot_ally_member_update_cron",               // callback function
    function ($bot, $data) {
        global $redis;
        //to disable return;
        return;
        if (!$redis->status()) return;
        
        $redis->SADD("stats:AllyMemberUpdate", (string)time());
        $alliance_key = "alliance:{$bot->ally_id}";
        $members = $redis->sMembers("{$alliance_key}:members");

        if (is_array($members)) {
            foreach($members as $member) {
                $uid = $redis->hGet('aliase', mb_strtoupper($member));
                $bot->addCall('includes/gPi.php', array('a' => $member), "PLAYER_{$uid}|DATA");
            }
            $bot->multiCall();
        }
        _destroy($data);
    }, 'alliance');
    
    // callbacks
    
    $bot->add_msg_hook(array(ALLYIN, OFFICERIN),
    "BridgeChat",               // command key
    "LouBot_bridge_chat",       // callback function
    false,                      // is a command PRE needet?
    '/.*/i',                    // optional regex for key
    function ($bot, $data) {
        global $redis;
        if (!$redis->status()) return;
        if ($bot->is_ally_user($data['user'])) {           
            if ($data["channel"] == ALLYIN)
                $bot->cross_allymsg($data['origin'], $data['user']);
            else if ($data["channel"] == OFFICERIN)
                $bot->cross_offimsg($data['origin'], $data['user']);
        };
        _destroy($data);
    }, 'alliance');
    
    $bot->add_alliance_hook("IncomingUpdate",       // command key
    "LouBot_incoming_update",                       // callback function
    function ($bot, $stat) {
        global $redis;
        if (empty($stat['id'])||$stat['id'] != INCOMINGS||!$redis->status()) return;
        $max_1h_time = $bot->get_timestamp() + 3600;

        $alliance_key = "alliance:{$bot->ally_id}";
        $incoming_key = "incoming";
        $incoming1h_key = "incoming1h";
        $messages = array();
        $incomings1h = array();
        
        if (!empty($stat['origin'])) {
            $_encoded_origin = Json\Json::Encode($stat['origin']);
            if ($redis->get("{$incoming_key}:{$alliance_key}:origin") != $_encoded_origin) {
                $redis->set("{$incoming_key}:{$alliance_key}:origin", $_encoded_origin);
                $bot->cross_sysmsg(INCOMINGS, count($stat['origin']));
            }
        } else {
            $redis->set("{$incoming_key}:{$alliance_key}:origin", "[]");
            $bot->cross_sysmsg(INCOMINGS, 0);
        }
        
        if (is_array($stat['data'])) foreach($stat['data'] as $incoming) {
            if ($incoming['arrived_time'] <= $max_1h_time) {
                array_push($incomings1h, $incoming['origin']);
            }
            if (!$incoming['sieging']) $incoming['possible'] = possible_unit($incoming['source']['coords'], $incoming['spotted_time'], $incoming['target']['coords'], $incoming['arrived_time']);
            else $incoming['possible'] = false;
            
            if (!$incoming['moongate'] && ($incoming['source']['continent'] != $incoming['target']['continent'])) {
                $incoming['possible'] = array('Navy', '0%');
            }
            
            $continent_key = "continent:{$incoming['continent']}";
            $incoming_json = Json\Json::Encode($incoming);
            $incoming_serial = $incoming['serial'];

            if ($redis->SETNX("{$incoming_key}:{$alliance_key}:{$continent_key}:{$incoming['target']['coords']}:{$incoming_serial}", $incoming_json)) {
                if (!$incoming['sieging'] && !$incoming['internal']) {
                    $redis->EXPIRE("{$incoming_key}:{$alliance_key}:{$continent_key}:{$incoming['target']['coords']}:{$incoming_serial}", INCTTL);
                    $moongate = ($incoming['moongate']) ? "\u26a0\ufe0fMoongate - " : '';
                    if ($incoming['possible'][0] == 'Senator') $alert = "\u203c\ufe0f";
                    else if ($incoming['possible'][0] == 'Artillery') $alert = "\u2757\ufe0f";
                    else if ($incoming['possible'][0] == 'Navy') $alert = "\u2693\ufe0f";
                    else $alert = '';

                    $uid = $bot->get_alliance_id($incoming['alliance']);
                    $short = $redis->HGET("alliance:{$uid}:data", 'short');
                    $messages[$incoming['defender']]["{$incoming['target']['coords']} - {$incoming['target']['name']}"][] = "{$moongate}{$incoming['attacker']}({$short}) from&#160;{$incoming['source']['coords']} eta:&#160;{$incoming['arrived']} possible:&#160;{$incoming['possible'][0]}@{$incoming['possible'][1]}{$alert}";
                } else $redis->EXPIRE("{$incoming_key}:{$alliance_key}:{$continent_key}:{$incoming['target']['coords']}:{$incoming_serial}", 3600);
            }
        }

        if (!empty($incomings1h)) {
            $_encoded_incomings1h = Json\Json::Encode($incomings1h);
            if ($redis->get("{$incoming1h_key}:{$alliance_key}:origin") != $_encoded_incomings1h) {
                $redis->set("{$incoming1h_key}:{$alliance_key}:origin", $_encoded_incomings1h);
                $bot->cross_sysmsg(INCOMINGS1H, count($incomings1h));
            }
        } else {
            $redis->set("{$incoming1h_key}:{$alliance_key}:origin", "[]");
            $bot->cross_sysmsg(INCOMINGS1H, 0);
        }
        
        if (!empty($messages)) foreach($messages as $defender => $targets) {
            $message = "<b>{$defender}</b> has new incomings:";
            foreach($targets as $target => $incs) {
                $message .= "&#10;<i>{$target}</i>";
                foreach($incs as $inc) {
                    $message .= "&#10;&#160;&#8226;&#160;" . $inc;
                }
            }
            $bot->debug("send Telegram: '{$message}'"); 
            $bot->telegram($message);
        }
        _destroy($stat);
    }, 'inandout');
    
    $bot->add_alliance_hook("OutgoingUpdate",       // command key
    "LouBot_outgoing_update",                       // callback function
    function ($bot, $stat) {
        global $redis;
        if (empty($stat['id'])||$stat['id'] != OUTGOINGS||!$redis->status()) return;
        $max_1h_time = $bot->get_timestamp() + 3600;

        $alliance_key = "alliance:{$bot->ally_id}";
        $outgoing_key = "outgoing";
        $outgoing1h_key = "outgoing1h";
        $messages = array();
        $outgoing1h = array();
        
        if (!empty($stat['origin'])) {
            $_encoded_origin = Json\Json::Encode($stat['origin']);
            if ($redis->get("{$outgoing_key}:{$alliance_key}:origin") != $_encoded_origin) {
                $redis->set("{$outgoing_key}:{$alliance_key}:origin", $_encoded_origin);
                $bot->cross_sysmsg(OUTGOINGS, count($stat['origin']));
            }
        } else {
            $redis->set("{$outgoing_key}:{$alliance_key}:origin", "[]");
            $bot->cross_sysmsg(OUTGOINGS, 0);
        }
        
        if (is_array($stat['data'])) foreach($stat['data'] as $outgoing) {
            if ($outgoing['arrived_time'] <= $max_1h_time) {
                array_push($outgoing1h, $outgoing['origin']);
            }
        }
        
        if (!empty($outgoing1h)) {
            $_encoded_outgoing1h = Json\Json::Encode($outgoing1h);
            if ($redis->get("{$outgoing1h_key}:{$alliance_key}:origin") != $_encoded_outgoing1h) {
                $redis->set("{$outgoing1h_key}:{$alliance_key}:origin", $_encoded_outgoing1h);
                $bot->cross_sysmsg(OUTGOINGS1H, count($outgoing1h));
            }
        } else {
            $redis->set("{$outgoing1h_key}:{$alliance_key}:origin", "[]");
            $bot->cross_sysmsg(OUTGOINGS1H, 0);
        }
        
        _destroy($stat);
    }, 'inandout');
    
    $bot->add_alliance_hook("AllianceMemberData",   // command key
    "LouBot_alliance_member_data_update",           // callback function
    function ($bot, $member) {
        global $redis;
        if (empty($member['id'])||$member['id'] != MEMBER||!$redis->status()) return;
        
        $member['data']['id'] = $bot->get_user_id($member['data']['name']);
        $user_key = "user:{$member['data']['id']}";
        $redis->HMSET("{$user_key}:data", array(
        'joined'             => strtotime($member['data']['joined']),
        'last_online'        => strtotime($member['data']['last_online']),
        'alliance_rank'      => $member['data']['alliance_rank'],
        'alliance_rank_id'   => $member['data']['arnum'],
        'title'              => $member['data']['title']
        ));
        _destroy($member);
    }, 'alliance');
    
    $bot->add_alliance_hook("AllianceMembers",      // command key
    "LouBot_alliance_members_update",               // callback function
    function ($bot, $members) {
        global $redis;
        if (empty($members['id'])||$members['id'] != MEMBERS||!$redis->status()) return;
        
        if (is_array($members['data']) && !$redis->exists("{$alliance_key}:_members")) {
            // members
            $alliance_key = "alliance:{$bot->ally_id}";
            $redis->RENAME("{$alliance_key}:members","{$alliance_key}:_members");
            
            foreach ($members['data'] as $member) {
                $sadd = $redis->sAdd("{$alliance_key}:members", $member);
            }
            
            $diff_old = $redis->sDiff("{$alliance_key}:_members","{$alliance_key}:members");
            if (is_array($diff_old)) foreach($diff_old as $old_member) {
                // do something with $old members
                $sadd = $redis->sAdd("{$alliance_key}:members_left", $old_member);
                $old_member_id = $bot->get_user_id($old_member);
                $user_key = "user:{$old_member_id}";
                $redis->HMSET("{$user_key}:data", array(
                'left'               => (string)time(),
                'alliance_rank'      => 'Left',
                'alliance_rank_id'   => 7
                ));
            }
            
            $diff_new = $redis->sDiff("{$alliance_key}:members","{$alliance_key}:_members");
            if (is_array($diff_new)) foreach($diff_new as $new_member) {
                // do something with $new members
                $sadd = $redis->sRem("{$alliance_key}:members_left", $new_member);
            }
            
            $redis->DEL("{$alliance_key}:_members");
        }
        _destroy($members);
    }, 'alliance');
    
    if(!function_exists('possible_unit')) {
        function possible_unit($source, $spotted_time, $target, $arrived_time) {
            if (!Bot\CotG_Bot::is_string_pos($source) || !Bot\CotG_Bot::is_string_pos($target)) return false;
            list($x1, $y1) = explode(':', $source, 2);
            list($x2, $y2) = explode(':', $target, 2);

            $dist = sqrt(($x1 - $x2)*($x1-$x2) + ($y1 - $y2)*($y1 - $y2));
            //$dist = sqrt((pow(($x1-$x2),2))+(pow(($y1-$y2),2))); // from Gordy
            
            // get bonus from alliance faith
            //$faith = get_alliance_faith ?
            
            $research = array(0,0.01,0.03,0.06,0.10,0.15,0.20,0.25,0.30,0.35,0.40,0.45,0.50);

            $units = array(
                /*'Navy'      => 5 + 60,*/ //??? 5 min a tile + 60min
                'Senator'   => 40,
                'Artillery' => 30,
                'Infantry'  => 20,
                'Cavalry'   => 10,
                'Scout'     => 8  
            );
            
            $running = $arrived_time - $spotted_time;
            $test = round($running / $dist / 60, 2);
            
            $closest_unit = 'unknown';
            foreach($units as $unit => $speed) {
                if ($test <= $speed) $closest_unit = $unit;
            }
            $research_match = 0;
            if ($closest_unit != 'unknown') foreach($research as $bonus) {
                if ($test <= $units[$closest_unit] - ($units[$closest_unit] * $bonus)) $research_match = $bonus;
            }
            return array($closest_unit, ($research_match * 100) . '%');
        }
    }
    
    $bot->add_privmsg_hook("LogMeIn",           // command key
                       "CotGBot_log_me_in",     // callback function
                       true,                    // is a command PRE needet?
                       '',                      // optional regex for key
    function ($bot, $data) {
        global $redis;
        
        if ($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
            $version_info = '';
            $deprecated = false;
            $outdated = false;
            $current = false;
            $beta = false;
            
            if (!$redis->status()) {
                $bot->add_privmsg("Sorry, no service available!", $data['user']);
            } else {
                $user_id = $bot->get_user_id($data['user']);
                $user_key = "user:{$user_id}";
                
                if ($data['params'][0] != '') {
                    $version = $data['params'][0];
                    $high_version = "6.0";
                    $low_version = "6.0";
                    $check_hversion = cpl_version_compare($version, $high_version);
                    $check_lversion = cpl_version_compare($version, $low_version);
                    if ($check_hversion == -1 && $check_lversion == -1) {
                        // deprecated
                        $deprecated = true;
                        $version_info = "Please update your '{$bot->bot_user_name} API'!";
                     } else if ($check_hversion == -1) {
                        // not current
                        $version_info = "An '{$bot->bot_user_name} API' update {$high_version} is available!";    
                    } else if ($check_hversion == 0) {
                        // current
                        $current = true;
                    } else if ($check_hversion == 1) {
                        // beta
                        $beta = true;
                        $version_info = 'Welcome Beta tester!';
                    }

                    $redis->HMSET("{$user_key}:api", array(
                    'last_used'           => (string)time(),
                    'version'             => $version,
                    'outdated'            => (int)$outdated
                    ));
                } else {
                    // outdated
                    $outdated = true;
                    $version = $data['params'][0];
                    $version_info = "Please update your '{$bot->bot_user_name} API'!";
                    $redis->HMSET("{$user_key}:api", array(
                    'version'             => $version,
                    'outdated'            => (int)$outdated
                    ));
                }
                $rank = $redis->hGet("{$user_key}:data", 'alliance_rank');
                $key = Bot\CotG_Bot::set_user_hash($data['user']);
                $bot->add_privmsg("Welcome back {$rank} {$data['user']}@{$key}", $data['user']);
                if ($version_info != '') $bot->add_privmsg($version_info, $data['user']);
            }
        } else if ($bot->is_ally_user($data['user'], defined('BOT_SISTER_ID')) && !$bot->is_himself($data['user'])) {
            $bot->add_privmsg("Please use the right Script!", $data['user']);
        } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
        _destroy($data);
    }, 'alliance');
    
    if (!function_exists('cpl_version_compare')) {
        function cpl_version_compare($ver1, $ver2, $operator = null) {
            $p = '#(\.0+)+($|-)#';
            $ver1 = preg_replace($p, '', $ver1);
            $ver2 = preg_replace($p, '', $ver2);
            return isset($operator) ? 
            version_compare($ver1, $ver2, $operator) : 
            version_compare($ver1, $ver2);
        }
    }
    
    $bot->add_alliance_hook("AllianceCityData",     // command key
    "LouBot_alliance_city_data_update",             // callback function
    function ($bot, $stat) {
        global $redis;
        if (empty($stat['id'])||$stat['id'] != CITY||!$redis->status()) return;
        
        $pos = $stat['data']['coords'];
        
        if (Bot\CotG_Bot::is_string_pos($pos)) {
            $redis->hMSet("city:{$pos}:data", $stat['data']);
        } 
        _destroy($stat);
    }, 'alliance');