<?php
    global $bot;
    $bot->add_category('user', array(), PUBLICY);
    
    // crons
    
    // callbacks
    
    $bot->add_msg_hook(array(PRIVATEIN, ALLYIN, OFFICIERIN),
    "LastChat",                 // command key
    "LouBot_last_chat",         // callback function
    false,                      // is a command PRE needet?
    '/.*/i',                    // optional regex for key
    function ($bot, $data) {
        global $redis;
        if (!$redis->status()) return;
        if ($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
            $uid = $bot->get_user_id($data['user']);
            $redis->HMSET("user:{$uid}:data", array(
            'lastchat' => date("m/d/Y H:i:s")
            ));
        };
        _destroy($uid, $data);
    }, 'user');
    
    $bot->add_msg_hook(array(PRIVATEIN, ALLYIN, OFFICIERIN),
    "Seen",                         // command key
    "LouBot_seen",                  // callback function
    true,                           // is a command PRE needet?
    '/^(lastseen|seen|chat)$/',     // optional regex for key
    function ($bot, $data) {
        global $redis;
        if (!$redis->status()) return;
        if ($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
            if ($bot->is_ally_user($data['params'][0])) {
                $uid = $bot->get_user_id($data['params'][0]);
                $nick = $redis->HGET("user:{$uid}:data", 'name');
                $lastchat = $redis->HGET("user:{$uid}:data", 'lastchat');
                $date = date('d.M.Y H:i:s', strtotime($lastchat));
                $message = ucfirst(mb_strtolower($data['params'][0])) . "'s letzter Chat war {$date}";
                $bot->reply_msg($data["channel"], $message, $data['user']);
            }
        } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
        _destroy($uid, $nick, $lastchat, $date, $message, $data);
    }, 'user');    