<?php
    global $bot;
    
    use CotG\Bot\Cron;
    use CotG\Bot\Log;
    
    $bot->add_category('operator', array(), OPERATOR);
    
    $bot->add_privmsg_hook("IsOpUser",              // command key
    "LouBot_is_op_user",                            // callback function
    true,                                           // is a command PRE needet?
    '',                                             // optional regex for key
    function ($bot, $data) {
        if($bot->is_op_user($data['user'])) {
            $user = array_shift($data['params']);
            if ($bot->is_op_user($user)) $bot->add_privmsg("{$user} is an OP!", $data['user']);
            else $bot->add_privmsg("{$user} is NOT an OP!", $data['user']);
        } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
        _destroy($data);
    }, 'operator');
    
    $bot->add_privmsg_hook("Debug",                 // command key
    "LouBot_debug",                                 // callback function
    true,                                           // is a command PRE needet?
    '',                                             // optional regex for key
    function ($bot, $data) {
        if($bot->is_op_user($data['user'])) {
            $debug = array_shift($data['params']);
            if (strtolower($debug) === 'on') {
                $bot->setDebug(true);
                $bot->log("Debug: " . (($bot->isDebug()) ? 'on' : 'off'), Log\Logger::NOTICE);
            }
            elseif (strtolower($debug) === 'off') {
                $bot->setDebug(false);
                $bot->log("Debug: " . (($bot->isDebug()) ? 'on' : 'off'), Log\Logger::NOTICE);
            }
            $bot->add_privmsg("Debug is: " . (($bot->isDebug()) ? 'on' : 'off'), $data['user']);
        } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
    }, 'operator');
    
    $bot->add_privmsg_hook("IsAllyMember",          // command key
    "LouBot_is_ally_member",                        // callback function
    true,                                           // is a command PRE needet?
    '',                                             // optional regex for key
    function ($bot, $data) {
        if($bot->is_op_user($data['user'])) {
            $user = array_shift($data['params']);
            if ($bot->is_ally_user($user)) $bot->add_privmsg("{$user} is an Member!", $data['user']);
            else $bot->add_privmsg("{$user} is NOT an Member!", $data['user']);
        } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
        _destroy($data);
    }, 'operator');
    
    $bot->add_privmsg_hook("ReloadHooks",           // command key
    "LouBot_reload_hooks",                          // callback function
    true,                                           // is a command PRE needet?
    '',                                             // optional regex for key
    function ($bot, $data) {
        if($bot->is_op_user($data['user'])) {
            if ($bot->reload()) $bot->add_privmsg("Funktionen neu geladen!", $data['user']);
        } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
        _destroy($data);
    }, 'operator');
    
    $bot->add_privmsg_hook("Whisper",               // command key
    "LouBot_whisper",                               // callback function
    true,                                           // is a command PRE needet?
    '/^(whisper|privat)$/i',                        // optional regex for key
    function ($bot, $data) {
        if($bot->is_op_user($data['user'])) {
            $user = array_shift($data['params']);
            $bot->add_privmsg(implode(' ', $data['params']), $user);
        } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
        _destroy($data);
    }, 'operator');
    
    $bot->add_privmsg_hook("DeleteKeys",            // command key
    "LouBot_delete_keys",                           // callback function
    true,                                           // is a command PRE needet?
    '',                                             // optional regex for key
    function ($bot, $data) {
        global $redis;
        //to disable return;
        #return;
        if (!$redis->status()) return;
        if ($data['params'][0] == '') return;
        if($bot->is_op_user($data['user'])) {
            $pattern = $data['params'][0];
            $test = ($data['params'][1] != '') ? true : false;
            $delete_keys = $redis->getKeys("{$pattern}");
            $count = 0;
            $expected = count($delete_keys);
            if (!empty($delete_keys)) foreach($delete_keys as $delete_key) {
                if (!$test)
                    if ($delete_key != '' && $redis->DEL("{$delete_key}")) $count++;
            }
            if (!$test) $bot->add_privmsg("{$count}/{$expected} keys deleted!", $data['user']);
            else $bot->add_privmsg("TestMode!!! '{$pattern}'/{$expected} keys not deleted!", $data['user']);
        } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
        _destroy($data);
    }, 'operator');
            
    $bot->add_privmsg_hook("Say",                   // command key
    "LouBot_say",                                   // callback function
    true,                                           // is a command PRE needet?
    '/^(sag|say)$/i',                               // optional regex for key
    function ($bot, $data) {
        if($bot->is_op_user($data['user'])) {
            $bot->add_allymsg(implode(' ', $data['params']));
        } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
    }, 'operator');
    
    $bot->add_privmsg_hook("SayOp",                 // command key
    "LouBot_sayop",                                 // callback function
    true,                                           // is a command PRE needet?
    '/^(sagop|sayop)$/i',                           // optional regex for key
    function ($bot, $data) {
        if($bot->is_op_user($data['user'])) {
            if ($bot->is_op_user($bot->bot_user_name))
                $bot->add_offimsg(implode(' ', $data['params']));
            else $bot->add_privmsg("No rights!", $data['user']);
        } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
        _destroy($data);
    }, 'operator');
    
    $bot->add_privmsg_hook("Time",                  // command key
    "LouBot_time",                                  // callback function
    true,                                           // is a command PRE needet?
    '/^(time|zeit)$/i',                             // optional regex for key
    function ($bot, $data) {
        if($bot->is_op_user($data['user'])) {
            $time = $bot->get_timestamp();
            $bot->add_privmsg($time, $data['user']);
        } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
        _destroy($data);
    }, 'operator');
    
    $bot->add_privmsg_hook("Ping",                  // command key
    "LouBot_ping_pong",                             // callback function
    false,                                          // is a command PRE needet?
    '/^[!]?(Ping|Pong)$/',                          // optional regex for key
    function ($bot, $data) {
        if($bot->is_himself($data['user'])) {
            $user = array_shift($data['params']);
            if ($data['command'] == 'Ping') $bot->add_privmsg("Pong {$user}", $bot->bot_user_name);
            else $bot->add_privmsg("Pong", $user);
        } else if ($bot->is_op_user($data['user']) && $data['command'][0] == PRE) {
            $bot->add_privmsg("Ping {$data['user']}", $bot->bot_user_name);
        } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
        _destroy($data);
    }, 'operator');
    
    $bot->add_privmsg_hook("KickHourly",            // command key
    "LouBot_kick_hourly",                           // callback function
    true,                                           // is a command PRE needet?
    '',                                             // optional regex for key
    function ($bot, $data) {
        global $redis;
        if (!$redis->status()) return;
        if($bot->is_op_user($data['user'])) {
            $argument = $data['params'][0];
            $bot->call_event(array('type' => TICK, 'name' => Cron\CronDaemon::HOURLY), $argument);
            $bot->add_privmsg("KickHourly running!", $data['user']);
        } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
        _destroy($data);
    }, 'operator');
    
    $bot->add_privmsg_hook("KickDaily",             // command key
    "LouBot_kick_daily",                            // callback function
    true,                                           // is a command PRE needet?
    '',                                             // optional regex for key
    function ($bot, $data) {
        global $redis;
        if (!$redis->status()) return;
        if($bot->is_op_user($data['user'])) {
            $argument = $data['params'][0];
            $bot->call_event(array('type' => TICK, 'name' => Cron\CronDaemon::DAILY), $argument);
            $bot->add_privmsg("KickDaily running!", $data['user']);
        } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
        _destroy($data);
    }, 'operator');
    
    $bot->add_privmsg_hook("AddExternalApiUser",    // command key
    "LouBot_add_api_user",                          // callback function
    true,                                           // is a command PRE needet?
    '',                                             // optional regex for key
    function ($bot, $data) {
        if($bot->is_op_user($data['user'])) {
            $user = array_shift($data['params']);
            $bot->add_privmsg('ApiKey: ' . $bot->set_hash($user, 'api'), $data['user']);
        } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
        _destroy($data);
    }, 'operator');
    
    $bot->add_privmsg_hook("DelExternalApiUser",    // command key
    "LouBot_del_api_user",                          // callback function
    true,                                           // is a command PRE needet?
    '',                                             // optional regex for key
    function ($bot, $data) {
        if($bot->is_op_user($data['user'])) {
            $user = array_shift($data['params']);
            $bot->add_privmsg('delete ApiKey: ' . $bot->del_hash($user, 'api'), $data['user']);
        } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
        _destroy($data);
    }, 'operator');                                                              