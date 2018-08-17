#!/usr/bin/php
<?php
date_default_timezone_set("Europe/London"); // CET / GMT+1
mb_internal_encoding("UTF-8");
declare(ticks = 1);

require_once("vendor/autoload.php");

// error reporting
error_reporting(E_ALL ^ E_NOTICE);
// determine enviroment
define('CLI', (bool) defined('STDIN'));

// gc_enable — Activates the circular reference collector
gc_enable();

// read argv
function arguments($argv) {
    $ARG = array();
    if (is_array($argv)) foreach ($argv as $arg) {
        if (strpos($arg, '--') === 0) {
            $compspec  = explode('=', $arg);
            $key       = str_replace('--', '', array_shift($compspec));
            $value     = join('=', $compspec);
            $ARG[$key] = $value;
            } elseif (strpos($arg, '-') === 0) {
            $key = str_replace('-', '', $arg);
            if (!isset($ARG[$key])) $ARG[$key] = true;
        }
    }

    return new ArrayObject($ARG, ArrayObject::ARRAY_AS_PROPS);
}

$_ARG = arguments($argv);

define('BOT_PATH', ((CLI) ? $_SERVER['PWD'] : $_SERVER['DOCUMENT_ROOT']) . DIRECTORY_SEPARATOR);
define('LOG_PATH', BOT_PATH . 'logs' . DIRECTORY_SEPARATOR);
define('DEBUG_PATH', BOT_PATH . 'debug' . DIRECTORY_SEPARATOR);
define('LOG_FILE', LOG_PATH . 'log.txt');
define('PERM_DATA', BOT_PATH . 'perm_data' . DIRECTORY_SEPARATOR);
define('DOKU_DATA', BOT_PATH . 'doku_data' . DIRECTORY_SEPARATOR);
define('FNC_DATA', BOT_PATH . 'bot_functions' . DIRECTORY_SEPARATOR);
define('BACK_DATA', BOT_PATH . 'backup_data' . DIRECTORY_SEPARATOR);
define('LANG_CACHE', BOT_PATH . 'lang_cache' . DIRECTORY_SEPARATOR);
define('HEAP_FILE', DEBUG_PATH . 'callgrind.out.');
define('MEMINFO_FILE', DEBUG_PATH . 'meminfo.out.');

//define('TELEGRAM_TOKEN', '');
//define('TELEGRAM_CHANNEL', '');

define('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.87 Safari/537.36');
define('USER_AGENT_INFO', '{"a":"Chrome","b":"Windows","c":"NT 10.0","d":"1920x1200","e":24,"f":"1920x1160","g":2730377724,"h":"Mitteleuropäische Zeit","i":"de","j":1322340077,"k":2849516782}');
define('USER_AGENT_GUID', '8ddc4cbc6d96d8d3c01133475a97cc23');

define('BOT_EMAIL', '');//Bot User Login
define('BOT_PASSWORD', '');//Bot User Password
define('BOT_EMERG_EMAIL', '');
define('BOT_OWNER', 'BloodHeart');
define('BOT_NAME', 'CotGBot');
define('BOT_ALLY_NAME', '');
define('BOT_ALLY_ID', 1);
//define('BOT_SISTER_NAME', '');
//define('BOT_SISTER_ID', 1);
define('BOT_USER_NAME', 'Cbot');
define('BOT_USER_ID', 1478);
define('BOT_LAST_CID', 10485994);
define('BOT_WORLD', 10);
define('BOT_IP', '188.68.36.167');
define('BOT_HOME', 'https://www.crownofthegods.com');
define('BOT_CHAT', 'wss://w10.crownofthegods.com/chat/');
define('BOT_SERVER', 'https://w10.crownofthegods.com');
define('BOT_LANG', 'en'); // your prefered language for login
//define('BOT_WEB_PORT', 1339);
//define('BOT_WS_PORT', 13391);
//define('BOT_SOCKET', 'ws://0.0.0.0:' . BOT_WS_PORT);

define('HALT', 'HALT');
define('STARTUP', 'STARTUP');
define('RUNNING', 'RUNNING');
define('CONNECTING', 'CONNECTING');
define('RECONNECTING', 'RECONNECTING');

define('CLAIMTTL', 86400); //24h
define('SETTLERTTL', 86400); //deprecated ?
define('SETTLETTL', 129600); //36h

define('INCTTL', 129600); //36h

define('PLAYER', 'PLAYER');
define('ALLIANCE', 'ALLIANCE');
define('CONTINENT', 'CONTINENT');

// redis database
#define('REDIS_CONNECTION', ((CLI) ? '/var/run/redis/redis.sock' : '127.0.0.1')); // localhost or socket
define('REDIS_CONNECTION', '127.0.0.1'); // localhost only
define('REDIS_NAMESPACE', 'cotg:'); // use custom prefix on all keys
define('REDIS_DB', 3);
define('REDIS_LOG_FILE', LOG_PATH . 'redis.txt');

define('PUBLICY', 'PUBLICY');
define('ALLYIN', 'ALLYIN');
define('ALLYOUT', 'ALLYOUT');
define('PRIVATEIN', 'PRIVATEIN');
define('PRIVATEOUT', 'PRIVATEOUT');
define('GLOBALIN', 'GLOBALIN');
define('SYSTEMIN', 'SYSTEMIN');
define('OFFICERIN', 'OFFICERIN');
define('OFFICEROUT', 'OFFICEROUT');
define('GAMEIN', 'GAMEIN');
define('GAMEOUT', 'GAMEOUT');
define('LEADIN', 'LEADIN');
define('LEADOUT', 'LEADOUT');
define('CROSSIN', 'CROSSIN');
define('CROSSOUT', 'CROSSOUT');
define('BOT', 'BOT');
define('INVOICE', 'INVOICE');
define('PRE', '!');
define('POLLTRIP', 1);
define('SPAMTTL', 15);
define('TIMEOUT', 15);
define('SYSTEM', '@');
define('NOTIFY', 'NOTIFY');
define('INCOMINGS', 'INCOMINGS');
define('OUTGOINGS', 'OUTGOINGS');
define('INCOMINGS1H', 'INCOMINGS1H');
define('OUTGOINGS1H', 'OUTGOINGS1H');
define('BLESSINGS', 'BLESSINGS');
define('MEMBER', 'MEMBER');
define('MEMBERS', 'MEMBERS');
define('USER', 'USER');
define('CRON', 'CRON');
define('TICK', 'TICK');
define('CHAT', 'chat');
define('RANGE', 'RANGE');
define('STATISTICS', 'STATISTICS');
define('MILITARY', 'MILITARY');
define('SHRINES', 'SHRINES');
define('DATA', 'DATA');
define('TROOPS', 'TROOPS');
define('SUBSTITUTE', 'SUBSTITUTE');
define('REINFORCEMENTS', 'REINFORCEMENTS');
define('CITY', 'CITY');
define('CITIES', 'CITIES');
define('SP', '|');
define('IGNORE_GLOBALIN', true);
define('MAX_LASTMESSAGES', 5);
define('OPERATOR', 'OPERATOR');
define('PRIVACY', 'PRIVACY');
define('POLL','POLL');
define('SETTLER','SETTLER');

// roles
class ROLES {
    const LEADER  = 1,
    SECOND_LEADER = 2,
    OFFICER       = 3,
    VETERAN       = 4,
    MEMBER        = 5,
    NEWBIE        = 6;
}

// lock
define('LOCK_FILE', BOT_PATH . str_replace('.php', '.lock', $_SERVER['PHP_SELF']));

// alice
define('ALICETIMEOUT', 3);
define('ALICETTL', 3);
define('ALICEID', 'f6d4afd83e34564d');//'cfeec954fe34dc4c');//f6d4afd83e34564d

// enable memprof
define('ENABLE_HOURLY_PROFILE', false);
define('MEMORY_CYCLE', 60);
// @see https://github.com/arnaud-lb/php-memory-profiler
if (ENABLE_HOURLY_PROFILE && function_exists('memprof_enable')) {
    memprof_enable();
}

if (version_compare(phpversion(), '5.6', '>')) {
    function _destroy(&...$args) {
        foreach ($args as &$arg) {
            if (is_object($arg) && method_exists($arg, '__destruct')) $arg->__destruct();
            unset($arg);
        }
    }
} else {
    function _destroy() {
        $args = debug_backtrace()[0]['args'];
        foreach ($args as &$arg) {
            if (is_object($arg) && method_exists($arg, '__destruct')) $arg->__destruct();
            unset($arg);
        }
    }
}

$bot = new CotG\Bot\CotG_Bot();

include_once('redis.php');
include_once('export.php');
include_once('dom.php');
include_once('aes_ctr.php');

/** @noinspection PhpUndefinedVariableInspection */
if ($redis) {
    if (!($redis_db_server = $redis->get("server:url")) || @$_ARG->forceDb) {
        // first time startup, set the proper server url
        $redis_db_server = BOT_SERVER;
        $redis->set("server:url", $redis_db_server);
    }
    if ($redis_db_server != BOT_SERVER && !$_ARG->forceDb) die('CotG world mishmash: please change db or force it: db=' . REDIS_DB . PHP_EOL);
}

$bot->run();