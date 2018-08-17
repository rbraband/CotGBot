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
    
    $bot->add_category('poll', array(), PRIVACY);
    
    // crons
    $bot->add_tick_event(Cron\CronDaemon::TICK0,    // Cron key
    "PollUpdate",                                   // command key
    "LouBot_poll_update_cron",                      // callback function
    function ($bot, $data) {
        global $redis;
        //to disable return;
        #return;
        if (!$redis->status()) return;
        
        $force_debug = false;
        
        $params = array(
            'cid'       => $bot->bot_last_city,
            'ss'        => $bot->session,
            'ai'        => 1,
            'world'     => ''
        );
        
        $bot->call('includes/poll2.php', $params, 'SYSTEM|POLL', $force_debug);
        _destroy($data);
    }, 'system');
    
    // callbacks
    
    $bot->add_poll_hook("PollTest",        // command key
    "LouBot_poll_test",                    // callback function
    function ($bot, $stat) {
        global $redis;
        //if (empty($stat['id'])||$stat['id'] != NOTIFY||!$redis->status()) return;
        //to disable return;
        return;
        $print = print_r($stat, true);

        $bot->log("POLL: {$print}", Log\Logger::DEBUG);
        _destroy($data);
    }, 'poll');

    $bot->add_poll_hook("PollNotify",        // command key
    "LouBot_poll_notify",                    // callback function
    function ($bot, $stat) {
        global $redis;
        if (empty($stat['id'])||$stat['id'] != NOTIFY||!$redis->status()) return;
        
        $notify = Json\Json::Encode($stat['data']);

        $bot->log("Notify: {$notify}", Log\Logger::NOTICE);
        _destroy($data);
    }, 'poll');
    
    $bot->add_poll_hook("PollAllianceUpdate", // command key
    "LouBot_poll_alliance_update",            // callback function
    function ($bot, $stat) {
        global $redis;
        if (empty($stat['id'])||$stat['id'] != ALLIANCE||!$redis->status()) return;
        
        $alliance_key = "alliance:{$bot->ally_id}";
        $rights = $redis->hGetAll("{$alliance_key}:rights");
        
        if ($rights != $stat['data']['rights']) {
            $redis->hMSet("{$alliance_key}:rights", $stat['data']['rights']);
            $bot->log("Update alliance rights: " . Json\Json::Encode($stat['data']['rights']), Log\Logger::NOTICE);
        }
        _destroy($data);
    }, 'poll');                                                       