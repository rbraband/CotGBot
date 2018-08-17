<?php
    global $bot;
    
    use CotG\Bot\Cron;
    
    //to disable return;
    #return;
    
    $bot->add_category('statistic', array(), PUBLICY);
    $bot->add_category('military', array(), PUBLICY);
    $bot->add_category('shrines', array(), PUBLICY);
    $bot->add_category('data', array(), PUBLICY);
    
    // crons
    
    /*
    https://w10.crownofthegods.com/includes/gPlA.php
    Player names
    
    https://w10.crownofthegods.com/includes/gM.php
    Mail in/out
    
    https://w10.crownofthegods.com/includes/gaCoT.php
    Alliance continet mail list
    
    https://w10.crownofthegods.com/includes/gFm.php + id=9183005401864&type=0
    Mail
    
    */
    
    $bot->add_cron_event(Cron\CronDaemon::DAILY,    // Cron key
    "DeleteOldStats",                               // command key
    "LouBot_delete_old_stats_cron",                 // callback function
    function ($bot, $data) {
        global $redis;
        if (!$redis->status()) return;
        
        //to disable return;
        return;
        
        $zsets = array('stats', 'inactive', 'new', 'left', 'lawless', 'overtake', 'castles', 'palace', 'rename', 'military', 'shrines');
        
        // delete stats < 72h
        // $latest = mktime(date("H") - 72, 0, 0, date("n"), date("j"), date("Y"));
        // delete stats < 1 month
        $latest = mktime(date("H"), 0, 0, date("n")-1, date("j"), date("Y"));
        // delete stats > 1 week
        // $latest = mktime(date("H"), 0, 0, date("n"), date("j") -7, date("Y"));
        
        foreach($zsets as $zset) {
            $count = 0;
            $keys = $redis->getKeys("*:{$zset}");
            
            if (is_array($keys)) foreach($keys as $key) {
                $count = $count + $redis->ZREMRANGEBYSCORE("{$key}", "-inf", "({$latest}");
                if ($count > 0) $bot->log("Delete {$count} entrys from ".count($keys)." {$zset} keys");
            }
        }
        _destroy($data);
    }, 'statistic');
    
    $bot->add_cron_event(Cron\CronDaemon::HOURLY,   // Cron key
    "GetContinentUpdate",                           // command key
    "LouBot_continent_update_cron",                 // callback function
    function ($bot, $data) {
        global $redis;
        //to disable return;
        #return;
        if (!$redis->status()) return;
        $redis->SADD("data:ContinentUpdate", (string)time());
        $_data = '{"a":"worldButton","b":"block","c":true,"d":'.$bot->get_server_time().',"e":"World"}';
        $_encrypted = $bot->get_encrypted($_data, ('Addxddx5DdAxxer' . $bot->bot_user_id . '2wz')); // key by CotG 1.937
        $bot->call('includes/gWrd.php', array('a' => $_encrypted), 'CONTINENT|STATISTICS');
        _destroy($data);
    }, 'statistic');
    
    $bot->add_cron_event(Cron\CronDaemon::HOURLY,   // Cron key
    "GetPlayerUpdate",                              // command key
    "LouBot_stats_all_player_update_cron",          // callback function
    function ($bot, $data) {
        global $redis;
        //to disable return;
        #return;
        if (!$redis->status()) return;
        $redis->SADD("stats:PlayerUpdate", (string)time());
        $bot->call('includes/gR.php', array('a' => '0'), 'PLAYER|STATISTICS'); //'a' => 0 = PLAYER
        _destroy($data);
    }, 'statistic');
    
    $bot->add_cron_event(Cron\CronDaemon::HOURLY,    // Cron key
    "GetContinentPlayerUpdate",                     // command key
    "LouBot_stats_continent_player_update_cron",    // callback function
    function ($bot, $data) {
        global $redis;
        //to disable return;
        #return;
        if (!$redis->status()) return;
        $redis->SADD("stats:ContinentPlayerUpdate", (string)time());
        $continents = $redis->sMembers('continents');
        
        sort($continents);
        
        if (is_array($continents)) {
            foreach($continents as $continent) {
                $delete_keys = $redis->getKeys("*:continent:{$continent}:members");
                if (!empty($delete_keys)) foreach($delete_keys as $delete_key) {
                    $redis->DEL("{$delete_key}");
                }
                $bot->addCall('includes/gR.php', array('a' => '0', 'b' => $continent), "CONTINENT_{$continent}|PLAYER|STATISTICS"); //'a' => 0 = PLAYER, 'b' => konti
            }
            $bot->multiCall();
        }
        _destroy($data);
    }, 'statistic');
    
    $bot->add_cron_event(Cron\CronDaemon::HOURLY,   // Cron key
    "GetAllianceMilitary",                          // command key
    "LouBot_get_alliance_military_cron",            // callback function
    function ($bot, $data) {
        global $redis;
        //to disable return;
        #return;
        if (!$redis->status()) return;
        $redis->SADD("stats:AllianceMilitary", (string)time());
        $bot->call('includes/gR.php', array('a' => '20', 'b' => '56'), 'ALLIANCE|MILITARY'); //'a' => 20 = MILITARY
        _destroy($data);
    }, 'military');
    
    $bot->add_cron_event(Cron\CronDaemon::HOURLY,   // Cron key
    "GetAllianceShrines",                           // command key
    "LouBot_get_alliance_shrines_cron",             // callback function
    function ($bot, $data) {
        global $redis;
        //to disable return;
        #return;
        if (!$redis->status()) return;
        $redis->SADD("stats:AllianceShrines", (string)time());
        $bot->call('includes/gR.php', array('a' => '14', 'b' => '56'), 'ALLIANCE|SHRINES'); //'a' => 14 = SHRINES
        _destroy($data);
    }, 'shrines');
    
    $bot->add_cron_event(Cron\CronDaemon::HOURLY,   // Cron key
    "GetContinentAllianceMilitary",                 // command key
    "LouBot_get_continent_alliance_military_cron",  // callback function
    function ($bot, $data) {
        global $redis;
        //to disable return;
        #return;
        if (!$redis->status()) return;
        $redis->SADD("stats:ContinentAllianceMilitary", (string)time());
        $continents = $redis->sMembers('continents');
        
        sort($continents);
        
        if (is_array($continents)) {
            foreach($continents as $continent) {
                $bot->addCall('includes/gR.php', array('a' => '20', 'b' => $continent), "CONTINENT_{$continent}|ALLIANCE|MILITARY"); //'a' => 20 = MILITARY, 'b' => konti
            }
            $bot->multiCall();
        }
        _destroy($data);
    }, 'military');
    
    $bot->add_cron_event(Cron\CronDaemon::HOURLY,   // Cron key
    "GetContinentAllianceShrines",                  // command key
    "LouBot_get_continent_alliance_shrines_cron",   // callback function
    function ($bot, $data) {
        global $redis;
        //to disable return;
        #return;
        if (!$redis->status()) return;
        $redis->SADD("stats:ContinentAllianceShrines", (string)time());
        $continents = $redis->sMembers('continents');
        
        sort($continents);
        
        if (is_array($continents)) {
            foreach($continents as $continent) {
                $bot->addCall('includes/gR.php', array('a' => '14', 'b' => $continent), "CONTINENT_{$continent}|ALLIANCE|SHRINES"); //'a' => 14 = SHRINES, 'b' => konti
            }
            $bot->multiCall();
        }
        _destroy($data);
    }, 'shrines');
    
    $bot->add_cron_event(Cron\CronDaemon::HOURLY,   // Cron key
    "GetAllianceUpdate",                            // command key
    "LouBot_stats_all_alliance_update_cron",        // callback function
    function ($bot, $data) {
        global $redis;
        //to disable return;
        #return;
        if (!$redis->status()) return;
        $redis->SADD("stats:AllianceUpdate", (string)time());
        $bot->call('includes/gR.php', array('a' => '1', 'b' => '56'), 'ALLIANCE|STATISTICS'); //'a' => 1 = ALLIANCE
        _destroy($data);
    }, 'statistic');
    
    $bot->add_cron_event(Cron\CronDaemon::HOURLY,   // Cron key
    "GetContinentAllianceUpdate",                   // command key
    "LouBot_stats_continent_alliance_update_cron",  // callback function
    function ($bot, $data) {
        global $redis;
        //to disable return;
        #return;
        if (!$redis->status()) return;
        $redis->SADD("stats:ContinentAllianceUpdate", (string)time());
        $continents = $redis->sMembers('continents');
        
        sort($continents);
        
        if (is_array($continents)) {
            foreach($continents as $continent) {
                $bot->addCall('includes/gR.php', array('a' => '1', 'b' => $continent), "CONTINENT_{$continent}|ALLIANCE|STATISTICS"); //'a' => 1 = ALLIANCE, 'b' => konti
            }
            $bot->multiCall();
        }
        _destroy($data);
    }, 'statistic');
    
    $bot->add_cron_event(Cron\CronDaemon::HOURLY,   // Cron key
    "GetAllianceData",                              // command key
    "LouBot_get_alliance_data_cron",                // callback function
    function ($bot, $data) {
        global $redis;
        //to disable return;
        #return;
        if (!$redis->status()) return;
        $redis->SADD("stats:GetAllianceData", (string)time());
        $alliances = $redis->hGetAll('alliances');

        if (is_array($alliances)) {
            foreach($alliances as $alliance => $key) {
                $bot->addCall('includes/gAd.php', array('a' => $alliance), "ALLIANCE_{$key}|DATA"); //'a' => alliance
            }
            $bot->multiCall();
        }
        _destroy($data);
    }, 'data');
    
    $bot->add_cron_event(Cron\CronDaemon::HOURLY,   // Cron key
    "GetPlayerUpdate",                              // command key
    "LouBot_get_player_data_cron",                  // callback function
    function ($bot, $data) {
        global $redis;
        //to disable return;
        #return;
        if (!$redis->status()) return;
        
        $redis->SADD("stats:GetPlayerData", (string)time());
        $aliases = $redis->hGetAll('aliase');
        
        if (is_array($aliases)) {
            foreach($aliases as $alias => $key) {
                $bot->addCall('includes/gPi.php', array('a' => $alias), "PLAYER_{$key}|DATA");
            }
            $bot->multiCall();
        }
        _destroy($data);
    }, 'data');
    
    // callback
    
    $bot->add_statistic_hook("ContinentsUpdate",    // command key
    "LouBot_continents_update",                     // callback function
    function ($bot, $stat) {
        global $redis;
        if (empty($stat['id'])||$stat['id'] != CONTINENT||!$redis->status()) return;
        $str_time = (string)time();
        if (is_array($stat['data']) && $stat['data']['id'] != 'all') {
            $continent_key = "continent:{$stat['data']['id']}";
            $bot->debug('Redis update statistic: ' . REDIS_NAMESPACE . $continent_key);
            // remember: dont know why 00 && 0 coexist
            $_var = array(
                'id'            => $stat['data']['id'],
                'free_count'    => $stat['data']['free_count'],
                'settled_count' => $stat['data']['settled_count'],
                'cities_count'  => $stat['data']['cities_count'],
                'castle_count'  => $stat['data']['castle_count'],
                'cavern_count'  => $stat['data']['cavern_count'],
                'boss_count'    => $stat['data']['boss_count']
            );

            $redis->HMSET("{$continent_key}:data", $_var);
            // we can check if no settle point taken and say this continent is not open!?
            
            // generate stat key: free_count|settled_count|cities_count|castle_count
            $stats = sprintf('%s|%d|%d|%d', $stat['data']['free_count'], $stat['data']['settled_count'], $stat['data']['cities_count'], $stat['data']['castle_count']);
            
            $last = $redis->zRange("{$continent_key}:stats", '-1',  '-1', true);
            if ($last && ($last[0] != $stats || date('Ymd') !== date('Ymd', strtotime($last[1]))) || !$last)
                $zadd = $redis->zAdd("{$continent_key}:stats", $str_time, $stats);
        } else if (is_array($stat['data']) && $stat['data']['id'] == 'all') {
            // remember the 56'th is all continents = world!
            $world_key = "world";
            $bot->debug('Redis update statistic: ' . REDIS_NAMESPACE . $world_key);

            $_var = array(
                'free_count'    => $stat['data']['free_count'],
                'settled_count' => $stat['data']['settled_count'],
                'cities_count'  => $stat['data']['cities_count'],
                'castle_count'  => $stat['data']['castle_count'],
                'cavern_count'  => $stat['data']['cavern_count'],
                'boss_count'    => $stat['data']['boss_count']
            );

            $redis->HMSET("{$world_key}:data", $_var);
            
            // generate stat key: free_count|settled_count|cities_count|castle_count
            $stats = sprintf('%s|%d|%d|%d', $stat['data']['free_count'], $stat['data']['settled_count'], $stat['data']['cities_count'], $stat['data']['castle_count']);
            
            $last = $redis->zRange("{$world_key}:stats", '-1',  '-1', true);
            if ($last && ($last[0] != $stats || date('Ymd') !== date('Ymd', strtotime($last[1]))) || !$last)
                $zadd = $redis->zAdd("{$world_key}:stats", $str_time, $stats);
        }
        _destroy($stat);
    }, 'statistic');
    
    $bot->add_statistic_hook("PlayerUpdate",        // command key
    "LouBot_player_update",                         // callback function
    function ($bot, $stat) {
        global $redis;
        if (empty($stat['id'])||$stat['id'] != PLAYER||!$redis->status()) return;
        $str_time = (string)time();
        $stat['data']['id'] = $bot->get_user_id($stat['data']['name']);
        $stat['data']['alliance_id'] = $bot->get_alliance_id($stat['data']['alliance']);
        if (is_array($stat['data']) && $stat['data']['id'] > 0) {
            $user_key = "user:{$stat['data']['id']}";
            $bot->debug('Redis update statistic: ' . REDIS_NAMESPACE . $user_key);
            $redis->HMSET("users", array(
                $stat['data']['name'] => $stat['data']['id']
            ));

            $_var = array(
                'id'        => $stat['data']['id'],
                'name'      => $stat['data']['name'],
                'rank'      => $stat['data']['rank'],
                'cities'    => $stat['data']['cities'],
                'points'    => $stat['data']['points'],
                'alliance'  => $stat['data']['alliance_id']
            );
            if (($_last_alliance = $redis->HGET("{$user_key}:data", 'alliance')) != $stat['data']['alliance_id'])
                $_var['last_alliance'] = $_last_alliance;

            $redis->HMSET("{$user_key}:data", $_var);
            
            // generate stat key: alliance_id|city_count|points|rank
            $stats = sprintf('%s|%d|%d|%d', $stat['data']['alliance_id'], $stat['data']['cities'], $stat['data']['points'], $stat['data']['rank']);
            
            $last = $redis->zRange("{$user_key}:stats", '-1',  '-1', true);
            if ($last && ($last[0] != $stats || date('Ymd') !== date('Ymd', strtotime($last[1]))) || !$last)
                $zadd = $redis->zAdd("{$user_key}:stats", $str_time, $stats);
        }
        _destroy($stat);
    }, 'statistic');
    
    $bot->add_statistic_hook("ContinentPlayerUpdate",        // command key
    "LouBot_continent_player_update",                        // callback function
    function ($bot, $stat) {
        global $redis;
        if (empty($stat['id'])||$stat['id'] != CONTINENT . '|' . PLAYER||!$redis->status()) return;
        $str_time = (string)time();
        $stat['data']['id'] = $bot->get_user_id($stat['data']['name']);
        $stat['data']['alliance_id'] = $bot->get_alliance_id($stat['data']['alliance']);
        $stat['data']['continent_id'] = intval($stat['continent']);
        if (is_array($stat['data']) && $stat['data']['id'] > 0) {
            $user_key = "user:{$stat['data']['id']}";
            $continent_key = "continent:{$stat['data']['continent_id']}";
            $alliance_key = "alliance:{$stat['data']['alliance_id']}";
            $bot->debug('Redis update statistic c'.$stat['data']['continent_id'].': ' . REDIS_NAMESPACE . $user_key);
            
            $_var = array(
                'id'        => $stat['data']['id'],
                'name'      => $stat['data']['name'],
                'rank'      => $stat['data']['rank'],
                'cities'    => $stat['data']['cities'],
                'points'    => $stat['data']['points'],
                'alliance'  => $stat['data']['alliance_id']
            );
            if (($_last_alliance = $redis->HGET("{$user_key}:{$continent_key}:data", 'alliance')) != $stat['data']['alliance_id'])
                $_var['last_alliance'] = $_last_alliance;
            
            $redis->HMSET("{$user_key}:{$continent_key}:data", $_var);
            
            // generate stat key: alliance_id|city_count|points|rank
            $stats = sprintf('%s|%d|%d|%d', $stat['data']['alliance_id'], $stat['data']['cities'], $stat['data']['points'], $stat['data']['rank']);
            
            $last = $redis->zRange("{$user_key}:{$continent_key}:stats", '-1',  '-1', true);
            if ($last && ($last[0] != $stats || date('Ymd') !== date('Ymd', strtotime($last[1]))) || !$last)
                $zadd = $redis->zAdd("{$user_key}:{$continent_key}:stats", $str_time, $stats);

            // add continent member
            if ($stat['data']['alliance_id'] > 0) $redis->SADD("{$alliance_key}:{$continent_key}:members", $stat['data']['name']);
        }
        _destroy($stat);
    }, 'statistic');
    
    $bot->add_statistic_hook("AllianceUpdate",      // command key
    "LouBot_alliance_update",                       // callback function
    function ($bot, $stat) {
        global $redis;
        if (empty($stat['id'])||$stat['id'] != ALLIANCE||!$redis->status()) return;
        $str_time = (string)time();
        $stat['data']['id'] = $bot->get_alliance_id($stat['data']['name']);
        if (is_array($stat['data']) && $stat['data']['id'] > 0) {
            $alliance_key = "alliance:{$stat['data']['id']}";
            $bot->debug('Redis update statistic: ' . REDIS_NAMESPACE . $alliance_key);
            $redis->HMSET("alliances", array(
                $stat['data']['name'] => $stat['data']['id']
            ));

            $redis->HMSET("{$alliance_key}:data", array(
                'id'        => $stat['data']['id'],
                'name'      => $stat['data']['name'],
                'rank'      => $stat['data']['rank'],
                'cities'    => $stat['data']['cities'],
                'points'    => $stat['data']['points'],
                'member'    => $stat['data']['member']
            ));
            
            // generate stat key: city_count|member_count|points|rank
            $stats = sprintf('%d|%d|%d|%d', $stat['data']['cities'], $stat['data']['member'], $stat['data']['points'], $stat['data']['rank']);
            
            $last = $redis->zRange("{$alliance_key}:stats", '-1',  '-1', true);
            if ($last && ($last[0] != $stats || date('Ymd') !== date('Ymd', strtotime($last[1]))) || !$last)
                $zadd = $redis->zAdd("{$alliance_key}:stats", $str_time, $stats);
        }
        _destroy($stat);
    }, 'statistic');
    
    $bot->add_military_hook("AllianceMilitary",      // command key
    "LouBot_alliance_military",                      // callback function
    function ($bot, $stat) {
        global $redis;
        if (empty($stat['id'])||$stat['id'] != ALLIANCE||!$redis->status()) return;
        $str_time = (string)time();
        $stat['data']['id'] = $bot->get_alliance_id($stat['data']['name']);
        if (is_array($stat['data']) && $stat['data']['id'] > 0) {
            $alliance_key = "alliance:{$stat['data']['id']}";
            $bot->debug('Redis update military: ' . REDIS_NAMESPACE . $alliance_key);
            $redis->HMSET("alliances", array(
                $stat['data']['name'] => $stat['data']['id']
            ));

            $redis->HMSET("{$alliance_key}:data", array(
                'military'    => $stat['data']['points']
            ));
            
            // generate stat key: points|rank
            $stats = sprintf('%d|%d', $stat['data']['points'], $stat['data']['rank']);
            
            $last = $redis->zRange("{$alliance_key}:military", '-1',  '-1', true);
            if ($last && ($last[0] != $stats || date('Ymd') !== date('Ymd', strtotime($last[1]))) || !$last)
                $zadd = $redis->zAdd("{$alliance_key}:military", $str_time, $stats);
        }
        _destroy($stat);
    }, 'military');
    
    $bot->add_shrines_hook("AllianceShrines",         // command key
    "LouBot_alliance_shrines",                       // callback function
    function ($bot, $stat) {
        global $redis;
        if (empty($stat['id'])||$stat['id'] != ALLIANCE||!$redis->status()) return;
        $str_time = (string)time();
        $stat['data']['id'] = $bot->get_alliance_id($stat['data']['name']);
        if (is_array($stat['data']) && $stat['data']['id'] > 0) {
            $alliance_key = "alliance:{$stat['data']['id']}";
            $bot->debug('Redis update shrines: ' . REDIS_NAMESPACE . $alliance_key);
            $redis->HMSET("alliances", array(
                $stat['data']['name'] => $stat['data']['id']
            ));

            $redis->HMSET("{$alliance_key}:data", array(
                'shrines'    => "{$stat['data']['total']}%/{$stat['data']['levels']}"
            ));
            
            // generate stat key: total|rank|levels|evara|vexemis|ibria|merius|ylanna|naera|cyndros|domdis
            $stats = sprintf('%d|%d|%d|%s|%s|%s|%s|%s|%s|%s|%s', 
                $stat['data']['total'], 
                $stat['data']['rank'],
                $stat['data']['levels'],
                $stat['data']['evara'],
                $stat['data']['vexemis'],
                $stat['data']['ibria'],
                $stat['data']['merius'],
                $stat['data']['ylanna'],
                $stat['data']['naera'],
                $stat['data']['cyndros'],
                $stat['data']['domdis']);
            
            $last = $redis->zRange("{$alliance_key}:shrines", '-1',  '-1', true);
            if ($last && ($last[0] != $stats || date('Ymd') !== date('Ymd', strtotime($last[1]))) || !$last)
                $zadd = $redis->zAdd("{$alliance_key}:shrines", $str_time, $stats);
        }
        _destroy($stat);
    }, 'shrines');
    
    $bot->add_statistic_hook("ContinentAllianceUpdate",      // command key
    "LouBot_continent_alliance_update",                      // callback function
    function ($bot, $stat) {
        global $redis;
        if (empty($stat['id'])||$stat['id'] != CONTINENT . '|' . ALLIANCE||!$redis->status()) return;
        $str_time = (string)time();
        $stat['data']['id'] = $bot->get_alliance_id($stat['data']['name']);
        $stat['data']['continent_id'] = intval($stat['continent']);
        if (is_array($stat['data']) && $stat['data']['id'] > 0) {
            $alliance_key = "alliance:{$stat['data']['id']}";
            $continent_key = "continent:{$stat['data']['continent_id']}";
            $bot->debug('Redis update statistic c'.$stat['data']['continent_id'].': ' . REDIS_NAMESPACE . $alliance_key);

            $redis->HMSET("{$alliance_key}:{$continent_key}:data", array(
                'id'        => $stat['data']['id'],
                'name'      => $stat['data']['name'],
                'rank'      => $stat['data']['rank'],
                'cities'    => $stat['data']['cities'],
                'points'    => $stat['data']['points'],
                'member'    => $stat['data']['member']
            ));
            
            // generate stat key: city_count|member_count|points|rank
            $stats = sprintf('%d|%d|%d|%d', $stat['data']['cities'], $stat['data']['member'], $stat['data']['points'], $stat['data']['rank']);
            
            $last = $redis->zRange("{$alliance_key}:{$continent_key}:stats", '-1',  '-1', true);
            if ($last && ($last[0] != $stats || date('Ymd') !== date('Ymd', strtotime($last[1]))) || !$last)
                $zadd = $redis->zAdd("{$alliance_key}:{$continent_key}:stats", $str_time, $stats);
        }
        _destroy($stat);
    }, 'statistic');
    
    $bot->add_military_hook("ContinentAllianceMilitary",       // command key
    "LouBot_continent_alliance_military",                      // callback function
    function ($bot, $stat) {
        global $redis;
        if (empty($stat['id'])||$stat['id'] != CONTINENT . '|' . ALLIANCE||!$redis->status()) return;
        $str_time = (string)time();
        $stat['data']['id'] = $bot->get_alliance_id($stat['data']['name']);
        $stat['data']['continent_id'] = intval($stat['continent']);
        if (is_array($stat['data']) && $stat['data']['id'] > 0) {
            $alliance_key = "alliance:{$stat['data']['id']}";
            $continent_key = "continent:{$stat['data']['continent_id']}";
            $bot->debug('Redis update military c'.$stat['data']['continent_id'].': ' . REDIS_NAMESPACE . $alliance_key);

            $redis->HMSET("{$alliance_key}:{$continent_key}:data", array(
                'military'    => $stat['data']['points']
            ));
            
            // generate stat key: points|rank
            $stats = sprintf('%d|%d', $stat['data']['points'], $stat['data']['rank']);
            
            $last = $redis->zRange("{$alliance_key}:{$continent_key}:military", '-1',  '-1', true);
            if ($last && ($last[0] != $stats || date('Ymd') !== date('Ymd', strtotime($last[1]))) || !$last)
                $zadd = $redis->zAdd("{$alliance_key}:{$continent_key}:military", $str_time, $stats);
        }
        _destroy($stat);
    }, 'military');
    
    $bot->add_military_hook("ContinentAllianceShrines",        // command key
    "LouBot_continent_alliance_shrines",                       // callback function
    function ($bot, $stat) {
        global $redis;
        if (empty($stat['id'])||$stat['id'] != CONTINENT . '|' . ALLIANCE||!$redis->status()) return;
        $str_time = (string)time();
        $stat['data']['id'] = $bot->get_alliance_id($stat['data']['name']);
        $stat['data']['continent_id'] = intval($stat['continent']);
        if (is_array($stat['data']) && $stat['data']['id'] > 0) {
            $alliance_key = "alliance:{$stat['data']['id']}";
            $continent_key = "continent:{$stat['data']['continent_id']}";
            $bot->debug('Redis update shrines c'.$stat['data']['continent_id'].': ' . REDIS_NAMESPACE . $alliance_key);

            $redis->HMSET("{$alliance_key}:{$continent_key}:data", array(
                'shrines'    => "{$stat['data']['total']}%/{$stat['data']['levels']}"
            ));
            
            // generate stat key: total|rank|levels|evara|vexemis|ibria|merius|ylanna|naera|cyndros|domdis
            $stats = sprintf('%d|%d|%d|%s|%s|%s|%s|%s|%s|%s|%s', 
                $stat['data']['total'], 
                $stat['data']['rank'],
                $stat['data']['levels'],
                $stat['data']['evara'],
                $stat['data']['vexemis'],
                $stat['data']['ibria'],
                $stat['data']['merius'],
                $stat['data']['ylanna'],
                $stat['data']['naera'],
                $stat['data']['cyndros'],
                $stat['data']['domdis']);
            
            $last = $redis->zRange("{$alliance_key}:{$continent_key}:shrines", '-1',  '-1', true);
            if ($last && ($last[0] != $stats || date('Ymd') !== date('Ymd', strtotime($last[1]))) || !$last)
                $zadd = $redis->zAdd("{$alliance_key}:{$continent_key}:shrines", $str_time, $stats);
        }
        _destroy($stat);
    }, 'shrines');
    
    $bot->add_data_hook("AllianceDataUpdate",           // command key
    "LouBot_alliance_data_update",                      // callback function
    function ($bot, $stat) {
        global $redis;
        if (empty($stat['id'])||$stat['id'] != ALLIANCE||!$redis->status()) return;
        $str_time = (string)time();
        $stat['data']['id'] = $bot->get_alliance_id($stat['data']['name']);
        if (is_array($stat['data']) && $stat['data']['id'] > 0 && $stat['data']['id'] == $stat['alliance']) {
            // update alliance
            $alliance_key = "alliance:{$stat['data']['id']}";
            $bot->debug('Redis update data: ' . REDIS_NAMESPACE . $alliance_key);
            
            $redis->HMSET("{$alliance_key}:data", array(
                'uid'        => $stat['data']['uid'],
                'short'      => $stat['data']['short'],
                'reputation' => $stat['data']['reputation']
            ));
            
            if(is_array($stat['data']['members']) && !$redis->exists("{$alliance_key}:_members")) {
                // members            
                $redis->RENAME("{$alliance_key}:members","{$alliance_key}:_members");
                
                foreach ($stat['data']['members'] as $member) {
                    $sadd = $redis->SADD("{$alliance_key}:members", $member);
                }
                
                $diff_old = $redis->SDIFF("{$alliance_key}:_members","{$alliance_key}:members");
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
                
                $diff_new = $redis->SDIFF("{$alliance_key}:members","{$alliance_key}:_members");
                if (is_array($diff_new)) foreach($diff_new as $new_member) {
                    // do something with $new members
                    $sadd = $redis->sRem("{$alliance_key}:members_left", $new_member);
                }
                $redis->DEL("{$alliance_key}:_members");
            }
        } else if($stat['alliance'] > 0) {
            // delete alliance
            $alliance_key = "alliance:{$stat['alliance']}";
            $bot->debug('Redis delete: ' . REDIS_NAMESPACE . $alliance_key);
            $delete_alliance_keys = $redis->getKeys("{$alliance_key}:*");
            if (!empty($delete_alliance_keys)) foreach($delete_alliance_keys as $delete_alliance_key) {
                $redis->DEL("{$delete_alliance_key}");
            }
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
            $bot->debug('Redis update data: ' . REDIS_NAMESPACE . $user_key);
            $redis->HMSET("{$user_key}:data", array(
            'uid'        => $stat['data']['uid']
            ));

            // cities            
            $redis->RENAME("{$user_key}:cities","{$user_key}:_cities");
            
            if (is_array($stat['data']['cities'])) foreach ($stat['data']['cities'] as $city => $city_data) {
                $city_data['player'] = $stat['data']['name'];
                if (($_last_player = $redis->HGET("city:{$city}:data", 'player')) != $stat['data']['name'])
                    $city_data['last_player'] = $_last_player;
                
                $sadd = $redis->SADD("{$user_key}:cities", $city);
                $redis->HMSET("city:{$city}:data", $city_data);
                
                // generate stat key: alliance_id|user_id|city_name|score|castle|temple|temple_level
                $stats = sprintf('%s|%s|%s|%d|%s|%s|%s', $stat['data']['alliance_id'], $stat['data']['id'], $city_data['name'], $city_data['score'], $city_data['castle'], $city_data['temple'], $city_data['temple_level']);
                
                $last = $redis->zRange("city:{$city}:stats", '-1',  '-1', true);
                if ($last && ($last[0] != $stats || date('Ymd') !== date('Ymd', strtotime($last[1]))) || !$last)
                    $zadd = $redis->zAdd("city:{$city}:stats", $str_time, $stats);
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