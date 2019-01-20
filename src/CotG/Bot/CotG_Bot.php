<?php
    namespace CotG\Bot;
    
    use CotG\Bot\Observer;
    use CotG\Bot\Plugins;
    use CotG\Bot\Json;
    use CotG\Bot\Log;
    
    class CotG_Bot extends Observer\Observer {
        
        protected $loop;
        protected $cron;
        protected $chat;
        protected $lock;
        protected $pcntl;
        protected $logger;
        protected $api;
        protected $client;
        protected $server;
        protected $browser;
        protected $session;
        protected $cookie_str;
        protected $tpnslu;
        protected $version;
        protected $offset;
        protected $socket;
        protected $router;
        protected $http;
        protected $cross;
        
        public $categories = [];
        public $last_chat = [];
        public $bot_user_name;
        public $bot_last_city;
        public $bot_user_id;
        public $owner = BOT_OWNER;
        public $ally_id;
        public $ally_name;
        public $ally_shortname;
        public $sister_id;
        public $state = HALT;
        public $xstate = HALT;
        public $curl;
        public $analyser;
        public $member_list = [];
        
        private $hooks = [];
        private $events = [];
        private $messages = [];
        private $last_message = [];
        private $stop = false;
        private $debug = false;
        private $is_connected = false;
        private $is_cross_connected = false;
        private $init = false;
        private $ppss = 0;
        private $cross_key;
        
        public function __construct() {
            global $_ARG;
            
            // logging
            $this->debug  = $_ARG->debug;
            $this->logger = Log\Logger::getInstance($this->debug);
            
            // locking
            try {
                $this->lock = new Lock\LockManager(LOCK_FILE);
            } catch (Lock\LockManagerRunningException $e) {
                die("Bot already running...\n");
            }
            
            // cron
            $this->cron = Cron\CronDaemon::factory($this);
            
            // chat
            $this->chat = Chat\Chat::factory($this);
            $this->chat->setLogger($this->logger);
            
            // analyser
            $this->analyser = Data\Analyser::factory($this);
            $this->analyser->setLogger($this->logger);
            
            // api
            $this->api = Data\API::factory($this);
            $this->api->setLogger($this->logger);
            
            // loop
            $this->loop   = \React\EventLoop\Factory::create();
            
            // web server
            if (defined('BOT_WEB_PORT')) {
                $this->socket = new \React\Socket\Server($this->loop);
                $this->http = new \React\Http\Server($this->socket, $this->loop);
                
                $this->http->on('request', function ($request, $response) {
                    $start = microtime(true);
                    $uuid = Data\UUID::v4();
                    $this->log("API request received!", Log\Logger::DEBUG);
                    $query = $request->getQuery();
                    if (!isset($query['apikey'])) {
                        // no apikey parameter passes, so we quit.
                        $response->writeHead(400, array('Content-Type' => 'text/plain; charset=utf-8'));
                        $response->end('Bad Request' . PHP_EOL);
                        $this->log("API bad request!", Log\Logger::DEBUG);
                        _destroy($request, $response);
                        return;
                    } else {
                        // check apikey with credentials or quit.
                        $user_id = $this->get_user_by_hash($query['apikey']);
                        $user = $this->get_user_name_by_id($user_id);
                        $this->log("API check access for " . (($user) ? $user : 'unknown') . ":" . (($user_id) ? $user_id : 'null') . "@{$query['apikey']}!", Log\Logger::DEBUG);
                        if (!$user || (defined('BOT_ALLY_ID') && !$this->is_ally_user($user))) {
                            $response->writeHead(401, array('Content-Type' => 'text/plain; charset=utf-8'));
                            $response->end('Unauthorized' . PHP_EOL);
                            $this->log("API unauthorized!", Log\Logger::DEBUG);
                            _destroy($request, $response);
                            return;
                        }
                    }
                    // go on
                    $method = $request->getMethod();
                    
                    if ($method == 'POST') {
                        $requestBody = '';
                        $headers = $request->getHeaders();
                        $contentLength = (int) $headers['Content-Length'];
                        $receivedData = 0;
                        if ($request->expectsContinue()) {
                            $response->writeContinue();
                            $request->close(); //Need I close request connection?
                        }
                        $request->on('data', function($data) 
                            use ($request, $response, &$requestBody, &$receivedData, $contentLength, $user, $method, $user_id) {
                                $requestBody .= $data;
                                $receivedData += strlen($data);
                                if ($receivedData >= $contentLength) {
                                    //parse_str($requestBody, $requestData);
                                    $requestData = urldecode($requestBody);
                                    $path = str_replace(DIRECTORY_SEPARATOR . BOT_WORLD, "", $request->getPath());
                                    $this->log("API user '{$user}' {$method} {$path}", Log\Logger::INFO);
                                    $result = $this->api->using($user_id, $requestData, $path, $this->debug);
                                    $options = array(
                                        'Content-Type' => 'application/x-javascript; charset=utf-8',
                                        'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
                                        'Last-Modified' => gmdate("D, d M Y H:i:s") . ' GMT', 
                                        'Cache-Control' => 'no-store, no-cache, must-revalidate', 
                                        'Cache-Control' => 'post-check=0, pre-check=0',
                                        'Pragma' => 'no-cache',
                                        'X-Server' => BOT_NAME . ' + React/Http Alpha',
                                        'x-content-type-options' => 'nosniff',
                                        'x-frame-options' => 'SAMEORIGIN',
                                        'X-Powered-By' => 'Reactphp/Http',
                                        'x-request-id' => $uuid,
                                        'x-runtime' => number_format(microtime(true) - $start, 6),
                                        'x-xss-protection' => '1; mode=block'
                                    );
                                
                                    switch ($result[0]) {
                                        case 200:
                                        case 202:
                                            if (isset($result[2]) && is_array($result[2])) $options = array_merge($options, $result[2]);
                                            $response->writeHead($result[0], $options);
                                            // wrap the output in the jsoncallback parameter if exist
                                            if (isset($query['jsoncallback']))
                                                $result[1] = "{$query['jsoncallback']}({$result[1]})";
                                            $response->end($result[1] . PHP_EOL);
                                            break;
                                        default:
                                            $options = array_merge($options, array('Content-Type' => 'text/plain; charset=utf-8'));
                                            $response->writeHead($result[0], $options);
                                            $response->end($result[1] . PHP_EOL);
                                    }
                                    _destroy($request, $response, $requestBody, $receivedData, $contentLength, $result, $start, $query, $user, $uuid, $path, $data, $options);
                                }
                        });
                    } else {
                        $path = str_replace(DIRECTORY_SEPARATOR . BOT_WORLD, "", $request->getPath());
                        $data = $query['data'];
                        $this->log("API user '{$user}' {$method} {$path}", Log\Logger::INFO);
                        $result = $this->api->using($user_id, $data, $path, $this->debug);
                        $options = array(
                            'Content-Type' => 'application/x-javascript; charset=utf-8',
                            'Expires' => 'Mon, 26 Jul 1997 05:00:00 GMT',
                            'Last-Modified' => gmdate("D, d M Y H:i:s") . ' GMT', 
                            'Cache-Control' => 'no-store, no-cache, must-revalidate', 
                            'Cache-Control' => 'post-check=0, pre-check=0',
                            'Pragma' => 'no-cache',
                            'X-Server' => BOT_NAME . ' + React/Http Alpha',
                            'x-content-type-options' => 'nosniff',
                            'x-frame-options' => 'SAMEORIGIN',
                            'X-Powered-By' => 'Reactphp/Http',
                            'x-request-id' => $uuid,
                            'x-runtime' => number_format(microtime(true) - $start, 6),
                            'x-xss-protection' => '1; mode=block'
                        );
                    
                        switch ($result[0]) {
                            case 202:
                            case 200:
                                if (isset($result[2]) && is_array($result[2])) $options = array_merge($options, $result[2]);
                                $response->writeHead($result[0], $options);
                                // wrap the output in the jsoncallback parameter if exist
                                if (isset($query['jsoncallback']))
                                    $result[1] = "{$query['jsoncallback']}({$result[1]})";
                                $response->end($result[1] . PHP_EOL);
                                break;
                            default:
                                $options = array_merge($options, array('Content-Type' => 'text/plain; charset=utf-8'));
                                $response->writeHead($result[0], $options);
                                $response->end($result[1] . PHP_EOL);
                        }
                        
                        _destroy($request, $response, $result, $options, $start, $query, $user, $uuid, $path, $data);
                    }
                });
            }
            // socket server
            if (defined('BOT_SOCKET')) {
                $this->server = new \Devristo\Phpws\Server\WebSocketServer(BOT_SOCKET, $this->loop, $this->logger);
                
                $this->loop->addPeriodicTimer((POLLTRIP * 60), function() {
                    $time = new \DateTime();
                    $string = $time->format("Y-m-d H:i:s");
                    $this->log("Broadcasting time PING to all clients: $string", Log\Logger::DEBUG);
                    $msg = array(
                        'a' => 0,
                        'b' => 'PING',
                        'c' => $string,
                        'd' => '_ALL_'
                    );
                    
                    $this->send_message($msg);
                    _destroy($string, $time, $msg);
                });
                
                $this->loop->addPeriodicTimer(POLLTRIP * 2, function() {
                    $members = [];
                    foreach($this->server->getConnections() as $client) {
                        if (!$client->getAuth()) {
                            $this->log("Check client auth: unknown@" . $client->getId(), Log\Logger::DEBUG);
                            // drop non authed clients
                            if ($client->getTimeout() <= (time() + 10)) { // drop after 10 seconds without auth
                                $this->log("Drop unauthorized client: unknown@" . $client->getId(), Log\Logger::DEBUG);
                                $client->close();
                            }
                        } else {
                            // message member list
                            $user = $client->getUserName();
                            if (defined('BOT_ALLY_ID') && $this->is_ally_user($user, defined('BOT_SISTER_ID'))) {
                                if (!$client->isClose()) {
                                    if ($client->isIdle())
                                        $_state = 'IDLE';
                                    else $_state = 'ONLINE';
                                } else $_state = 'CLOSE';
                                
                                $members[$user] = array(
                                    'name' => $user, 
                                    'alliance' => $client->getAllianceName(), 
                                    'officer' => $this->has_rank($user, \ROLES::OFFICER, defined('BOT_SISTER_ID')), 
                                    'state' => $_state);
                            }
                        }
                    }
                    ksort($members, SORT_NATURAL | SORT_FLAG_CASE);
                    
                    if ($this->member_list !== $members) {
                        $this->member_list = $members;
                        $msg = array(
                        'a' => 0,
                        'b' => 'MEMBERS',
                        'c' => $members,
                        'd' => '_ALL_'
                        );
                        $this->send_message($msg);
                    }
                    _destroy($client, $user, $_state, $members, $msg);
                });
                
                // when a client connects
                $this->server->on("connect", function(\Devristo\Phpws\Protocol\WebSocketTransportInterface $client) {
                    $this->log("Set client auth timeout: unknown@" . $client->getId(), Log\Logger::DEBUG);
                    $client->setTimeout(time() + 10);
                });
                
                // get message the client sends
                $this->server->on("message", function(\Devristo\Phpws\Protocol\WebSocketTransportInterface $sender, \Devristo\Phpws\Messaging\WebSocketMessageInterface $msg) {
                    $message = Json\Json::Decode($msg->getData());
                    $message->b = strip_tags($message->b, '<coords><player><report><alliance>');
                    if ($sender->getAuth()) {
                        $sender_name = $sender->getUserName();
                        $sender_ally_name = $sender->getAllianceName();
                        if (defined('BOT_ALLY_ID') && !$this->is_ally_user($sender_name, defined('BOT_SISTER_ID'))) {
                            $this->log("Drop unauthorized client: {$sender_name}@" . $sender->getId(), Log\Logger::DEBUG);
                            $sender->close();
                            _destroy($sender);
                        } else {
                            switch (intval($message->a)) {
                                case 0:
                                    switch (strtoupper($message->b)) {
                                        case 'PONG':
                                            $sender->unSetIdle();
                                            $sender->unSetClose();
                                            break;
                                        case 'IDLE':
                                            if (!$sender->isIdle())
                                                $sender->setIdle(time());
                                            break;
                                        case 'CLOSE':
                                            if (!$sender->isClose())
                                                $sender->setClose(time());
                                            break;
                                        case 'INCOMINGS':
                                        case 'INCOMINGS1H':
                                        case 'OUTGOINGS':
                                        case 'OUTGOINGS1H':
                                            if (!defined('BOT_SISTER_ID') || !$this->has_rank($sender_name, \ROLES::OFFICER, defined('BOT_SISTER_ID'))) {
                                                $this->log("Drop unauthorized client: {$sender_name}@" . $sender->getId(), Log\Logger::DEBUG);
                                                $sender->close();
                                                _destroy($sender);
                                            } else {
                                                $this->log("Broadcasting incoming sysmsg to authorized clients: " . $message->b, Log\Logger::DEBUG);
                                                $this->push_lastmessage($message, $message->a, $message->d, $message->b);
                                                $rights = $this->get_alliance_rights_by_id($this->get_alliance_id($message->d));
                                                $_io_right = (isset($rights['io'])) ? $rights['io'] : \ROLES::OFFICER;
                                                $_io1h_right = (isset($rights['io1h'])) ? $rights['io1h'] : \ROLES::OFFICER;
                                                
                                                foreach($this->server->getConnections() as $client) {
                                                    if ($client->getAuth()) {
                                                        $alliance_name = $client->getAllianceName();
                                                        if ($alliance_name !== $message->d) {// not same alliance
                                                            $user = $client->getUserName();
                                                            if (defined('BOT_ALLY_ID') && $this->is_ally_user($user, defined('BOT_SISTER_ID'))) {
                                                                $rank = $this->get_rank_by_id($client->getUserId());
                                                                $_message = (array)$message;
                                                                if (strtoupper($message->b) == 'INCOMINGS1H') {
                                                                    if ($rank <= $_io1h_right && !($rank <= $_io_right)) {
                                                                        $_message['b'] = 'INCOMINGS';
                                                                        $this->log("Cross sysmsg to {$user}: " . $message->b, Log\Logger::DEBUG);
                                                                        $client->sendString(Json\Json::Encode($_message));
                                                                    }
                                                                } elseif (strtoupper($message->b) == 'OUTGOINGS1H') {
                                                                    if ($rank <= $_io1h_right && !($rank <= $_io_right)) {
                                                                        $_message['b'] = 'OUTGOINGS';
                                                                        $this->log("Cross sysmsg to {$user}: " . $message->b, Log\Logger::DEBUG);
                                                                        $client->sendString(Json\Json::Encode($_message));
                                                                    }
                                                                } elseif ($rank <= $_io_right) {
                                                                    $this->log("Cross sysmsg to {$user}: " . $message->b, Log\Logger::DEBUG);
                                                                    $client->sendString(Json\Json::Encode($_message));
                                                                }
                                                            }
                                                        }
                                                    }
                                                }
                                                _destroy($_message);
                                            }
                                            break;
                                        case 'LASTMSG':
                                            $message_type = strtoupper($message->c);
                                            switch ($message_type) {
                                                case 'INCOMINGS':
                                                case 'OUTGOINGS':
                                                    if (defined('BOT_SISTER_ID')) {
                                                        $rank = $this->get_rank_by_id($sender->getUserId());
                                                        $lastmessages = [];
                                                        $sister_ids = explode('|', $this->sister_id);
                                                        foreach ($sister_ids as $sister_id) {
                                                            $rights = $this->get_alliance_rights_by_id($sister_id);
                                                            $_io_right = (isset($rights['io'])) ? $rights['io'] : \ROLES::OFFICER;
                                                            $_io1h_right = (isset($rights['io1h'])) ? $rights['io1h'] : \ROLES::OFFICER;
                                                            if ($rank <= $_io_right) {
                                                                $_lastmessages = $this->get_lastmessages($message->a, $this->get_alliance_name_by_id($sister_id), $message_type, 1);
                                                            } else if ($rank <= $_io1h_right) {
                                                                $_message_type = (($message_type == 'OUTGOINGS') ? 'OUTGOINGS1H' : 'INCOMINGS1H');
                                                                $_lastmessages = $this->get_lastmessages($message->a, $this->get_alliance_name_by_id($sister_id), $_message_type, 1);
                                                            }
                                                            if (!empty($_lastmessages)) $lastmessages = array_merge($lastmessages, $_lastmessages);
                                                        }
                                                        if (!empty($lastmessages)) {
                                                            $this->log("Send last sysmsg ({$message_type}/{$message->d}) to client: " . $sender->getUserName(), Log\Logger::DEBUG);
                                                            foreach($lastmessages as $lastmessage)
                                                                $sender->sendString($lastmessage);
                                                        }
                                                    }
                                                    break;
                                            }
                                            break;
                                    }
                                    break;
                                case 5:
                                    $this->log("Broadcasting funmsg to all clients: " . $message->b, Log\Logger::DEBUG);
                                    $msg = array(
                                        'a' => 5,
                                        'b' => $message->b,
                                        'c' => $sender_name,
                                        'd' => $sender_ally_name
                                    );
                                    $this->push_lastmessage($msg, $message->a);
                                    foreach($this->server->getConnections() as $client) {
                                        if ($client->getAuth()) {
                                            $user = $client->getUserName();
                                            if (!defined('BOT_ALLY_ID') || $this->is_ally_user($user, defined('BOT_SISTER_ID'))) {
                                                $client->sendString(Json\Json::Encode($msg));
                                            }
                                        }
                                    }
                                    $data = array(
                                        'a' => 3,
                                        'b' =>  array(
                                            'a' => 6,
                                            'b' => $sender_name,
                                            'c' => null,
                                            'd' => $message->b
                                        )
                                    );
                                    $this->chat->using(Json\Json::Encode($data), $this->debug);
                                    break;
                                //deprecated
                                case 6:
                                    break;
                                //deprecated
                                case 7:
                                    break;
                                case 8:
                                    break;
                                //cross ally chat msg
                                case 9:
                                    $this->log("Broadcasting allymsg to all clients: " . $message->b, Log\Logger::DEBUG);

                                    $this->push_lastmessage($message, $message->a, $message->d);
                                    foreach($this->server->getConnections() as $client) {
                                        if ($client->getAuth()) {
                                            $alliance_name = $client->getAllianceName();
                                            if ($alliance_name !== $message->d) { // not same alliance
                                                $user = $client->getUserName();
                                                if (defined('BOT_ALLY_ID') && $this->is_ally_user($user, defined('BOT_SISTER_ID'))) {
                                                    $client->sendString(Json\Json::Encode($message));
                                                }
                                            }
                                        }
                                    }
                                    break;
                                //cross offi chat msg
                                case 10:
                                    if (!defined('BOT_ALLY_ID') || !$this->has_rank($sender_name, \ROLES::OFFICER, defined('BOT_SISTER_ID'))) {
                                        $this->log("Drop unauthorized client: {$sender_name}@" . $sender->getId(), Log\Logger::DEBUG);
                                        $sender->close();
                                        _destroy($sender);
                                    } else {
                                        $this->log("Broadcasting offimsg to all clients: " . $message->b, Log\Logger::DEBUG);

                                        $this->push_lastmessage($message, $message->a, $message->d);
                                        foreach($this->server->getConnections() as $client) {
                                            if ($client->getAuth()) {
                                                $alliance_name = $client->getAllianceName();
                                                if ($alliance_name !== $message->d) { // not same alliance
                                                    $user = $client->getUserName();
                                                    if (defined('BOT_ALLY_ID') && $this->has_rank($user, \ROLES::OFFICER, defined('BOT_SISTER_ID'))) {
                                                        $client->sendString(Json\Json::Encode($message));
                                                    }
                                                }
                                            }
                                        }
                                    }
                                    break;
                            }
                        }
                    } else { // unauthorized
                        if ($message->a == 0 && $message->b != '') {
                            $user_id = $this->get_user_by_hash($message->b);
                            $user = ($this->get_user_name_by_id($user_id)) ?: 'unknown';
                            $this->log("CHAT check access for {$user}:" . (($user_id) ? $user_id : 'null')  . "@{$message->b}!", Log\Logger::DEBUG);
                            if ($user == 'unknown' || (defined('BOT_ALLY_ID') && !$this->is_ally_user($user, defined('BOT_SISTER_ID')))) {
                                $this->log("CHAT kick {$user}:" . (($user_id) ? $user_id : 'null')  . " not a Member!", Log\Logger::DEBUG);
                                $sender->close();
                                _destroy($sender);
                            } else {
                                $alliance_id = $this->get_user_aliance_by_id($user_id);
                                $alliance_name = $this->get_alliance_name_by_id($alliance_id);
                                $sender->setAuth(true);
                                $sender->unSetTimeout();
                                $sender->unSetIdle();
                                $sender->unSetClose();
                                $sender->setUserId($user_id);
                                $sender->setUserName($user);
                                $sender->setAllianceName($alliance_name);
                                $sender->setAllianceId($alliance_id);
                            }
                        }
                    }
                    _destroy($sender_name, $sender_ally_name, $client, $alliance_id, $alliance_name, $user, $user_id, $message, $msg, $lastmessages, $lastmessage);
                });
                
                $this->server->on("handshake", function(\Devristo\Phpws\Protocol\WebSocketTransportInterface $transport, \Devristo\Phpws\Protocol\Handshake $handshake){
                    $handshake->getResponse()->getHeaders()->addHeaderLine("X-WebSocket-Server", BOT_NAME);
                });
            }
            
            // socket client
            
            // connection timeout watchdog
            $timeout = $this->loop->addPeriodicTimer(TIMEOUT, function() {
                
                $this->log("Chat timeout!", Log\Logger::CRIT);
                $this->stop('timeout');
            });
            
            $this->client = new \Devristo\Phpws\Client\WebSocket(BOT_CHAT, $this->loop, $this->logger, ['headers' => array(
                'Accept-Encoding' => 'gzip, deflate',
                'Accept-Language' => 'de-DE,de;q=0.8,en-US;q=0.6,en;q=0.4',
                'User-Agent' => USER_AGENT,
                'Pragma' => 'no-cache',
                'Cache-Control' => 'no-cache', 
                'Origin' => BOT_SERVER
            //You can set "bindto" to "0:0" to force use IPv4 instead of IPv6. 
            //And probably "[0]:0" to force use IPv6, thou this I couldn't test.
            ),'socket' => array('bindto' => ((defined('BOT_IP')) ? BOT_IP : '0:0'))]);
            
            $this->client->on("request", function () {
                
                $this->log("Chat request object created!", Log\Logger::NOTICE);
            });
            
            $this->client->on("handshake", function () {
                
                $this->log("Chat handshake received!", Log\Logger::NOTICE);
            });
            
            $this->client->on("connect", function () use($timeout) {
                $this->log("Chat connected!", Log\Logger::NOTICE);
                $auth = array(
                    'a' => 0,
                    'b' => $this->tpnslu
                );
                $this->state = CONNECTING;
                $this->client->send(Json\Json::Encode($auth));
                $this->log("Chat login with creds: " . $this->tpnslu, Log\Logger::NOTICE);
                // kill the timeout watchdog
                $this->loop->cancelTimer($timeout);
                $this->is_connected = true;
                _destroy($auth);
            });
            
            $this->client->on("message", function ($message) {// main routine for incomming msg
                $data = $message->getData();
                if (!empty($data)) $json = Json\Json::Decode($data, true); // second parameter to true toAssoc 

                $this->state = RUNNING;
                
                // later do this with event like FIRSTCHAT
                if ($json['a'] == 0 && $json['b'] == 0) {
                    // {"a":"0","b":"0"}
                    $this->log("Chat data kicking: " . $data, Log\Logger::WARN);
                    $this->stop('unauthorized');
                } else if (!$this->init) $this->init = true;
                
                // foobar
                if ($json['a'] != 3)
                    $this->log("Chat data unknown: " . $data, Log\Logger::NOTICE);
                // no idea for a better place?
                if (!(IGNORE_GLOBALIN && $json['b']['a'] == 1))
                    // use chat
                    $this->chat->using($data, $this->debug);
                _destroy($message, $data, $json);
            });
            
            $this->client->on("close", function () use($timeout) {
                $this->log("Chat closed!", Log\Logger::CRIT);
                $this->log("Chat state: RECONNECTING", Log\Logger::NOTICE);
                $this->state = RECONNECTING;
                //\Clue\React\Block\sleep(1.0, $this->loop); // not working!?
                $this->is_connected = false;
                $this->client->open();
            });
            
            if (defined('BOT_CROSS')) {
                // cross client
                
                //connection timeout watchdog
                $cross_timeout = $this->loop->addPeriodicTimer(TIMEOUT, function() {
                    
                    $this->log("Cross timeout!", Log\Logger::CRIT);
                                             
                });
                $this->cross = new \Devristo\Phpws\Client\WebSocket(BOT_CROSS, $this->loop, $this->logger, ['headers' => array(
                    'Accept-Encoding' => 'gzip, deflate',
                    'Accept-Language' => 'de-DE,de;q=0.8,en-US;q=0.6,en;q=0.4',
                    'User-Agent' => USER_AGENT,
                    'Pragma' => 'no-cache',
                    'Cache-Control' => 'no-cache', 
                    'Origin' => BOT_SERVER
                //You can set "bindto" to "0:0" to force use IPv4 instead of IPv6. 
                //And probably "[0]:0" to force use IPv6, thou this I couldn't test.
                ),'socket' => array('bindto' => ((defined('BOT_IP')) ? BOT_IP : '0:0'))]);
                
                $this->cross->on("request", function () {
                    
                    $this->log("Cross object created!", Log\Logger::NOTICE);
                });
                
                $this->cross->on("handshake", function () {
                    
                    $this->log("Cross handshake received!", Log\Logger::NOTICE);
                });
                
                $this->cross->on("connect", function () use($cross_timeout) {
                    $this->log("Cross connected!", Log\Logger::NOTICE);
                    $auth = array(
                        'a' => 0,
                        'b' => $this->cross_key
                    );
                    $this->xstate = CONNECTING;
                    $this->cross->send(Json\Json::Encode($auth));
                    $this->log("Cross login with creds: " . $this->cross_key, Log\Logger::NOTICE);
                    // kill the timeout watchdog
                    $this->loop->cancelTimer($cross_timeout);
                    $this->is_cross_connected = true;
                    _destroy($auth);
                });
                
                $this->cross->on("message", function ($message) {// main routine for incomming msg
                    $data = $message->getData();
                    if (!empty($data)) $json = Json\Json::Decode($data, true); // second parameter to true toAssoc 
                    
                    $this->xstate = RUNNING;
                    
                    _destroy($message, $data, $json);
                });
                
                $this->cross->on("close", function () use($cross_timeout) {
                    $this->log("Cross closed!", Log\Logger::CRIT);
                    $this->log("Cross state: RECONNECTING", Log\Logger::NOTICE);
                    $this->xstate = RECONNECTING;
                    //\Clue\React\Block\sleep(1.0, $this->loop); // not working!?
                    $this->is_cross_connected = false;
                    $this->cross->open();
                });
            }
            
            $memory = 0;
            $last_profile = time();
            $this->loop->addPeriodicTimer(MEMORY_CYCLE, function () use(&$memory, &$last_profile) {
                if (!ENABLE_HOURLY_PROFILE) gc_collect_cycles();
                $_memory = round((memory_get_usage() / 1024 / 1024), 2);
                if ($memory == $_memory) return;
                $formatted = number_format($_memory, 2, ((BOT_LANG == 'de') ? ',' : ','), '').'MB';
                $this->log("Current memory usage: {$formatted}", Log\Logger::NOTICE);
                $memory = $_memory;
                $next_profile = $last_profile + (60 * 60);
                if (ENABLE_HOURLY_PROFILE && time() >= $next_profile) {
                    if (function_exists('memprof_enable') && memprof_enabled()) {
                        $this->log("Dump callgrind (".HEAP_FILE . date("B").")!", Log\Logger::DEBUG);
                        memprof_dump_callgrind(fopen(HEAP_FILE . date("B"), 'w'));
                        $last_profile = time();
                    } else if(function_exists('meminfo_objects_summary')) {
                        $this->log("Dump memory objects (".MEMINFO_FILE . date("B").")!", Log\Logger::DEBUG);
                        meminfo_structs_size(fopen(MEMINFO_FILE . date("B"), 'w'));
                        $last_profile = time();
                    }
                }
                _destroy($_memory, $next_profile);
            });
            
            $this->loop->addPeriodicTimer(POLLTRIP, function () {
                $this->cron->check();
                $this->chat->check();
                $msg = $this->get_message();
                if ($msg !== null) $this->client->send($msg);
                _destroy($msg);
            });
            
            // browser
            $this->browser = new \Clue\React\Buzz\Browser($this->loop); 
            
            // curl
            $this->curl = new \KHR\React\Curl\Curl($this->loop);
            $this->curl->client->setMaxRequest(10);
            $this->curl->client->setSleep(10, 1.0, false);
            $this->curl->client->enableHeaders();
            $this->curl->client->setBaseUrl(BOT_SERVER);
            $this->curl->client->setCurlOption([
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_DNS_USE_GLOBAL_CACHE => 0,
                //CURLOPT_DNS_SERVERS => '8.8.8.8,8.8.8.4',
                //CURLOPT_DNS_CACHE_TIMEOUT => 3600,
                CURLOPT_AUTOREFERER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_COOKIESESSION => true,
                CURLOPT_VERBOSE => $this->debug,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => 10,
                //CURLOPT_COOKIEFILE => PERM_DATA . 'cookies.txt',
                //CURLOPT_COOKIEJAR => PERM_DATA . 'cookies.txt',
                CURLOPT_ENCODING => 'gzip,deflate',
                CURLOPT_REFERER => BOT_SERVER . '/',
                CURLOPT_HTTPHEADER => array (
                    "Accept: */*",
                    "Host: " . parse_url(BOT_SERVER, PHP_URL_HOST),
                    "Origin: " . BOT_SERVER, 
                    "Accept-Language: de-DE,de;q=0.8,en-US;q=0.6,en;q=0.4",
                    "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
                    "Content-Encoding: " . uniqid(),
                    "Cache-Control: no-cache", 
                    "Pragma: no-cache",
                    "X-Requested-With: XMLHttpRequest",
                    "pp-ss: " . $this->ppss,
                ),
                CURLOPT_USERAGENT => USER_AGENT,
                ]);
            if (defined('BOT_IP')) {
                $this->curl->client->setCurlOption([CURLOPT_INTERFACE => BOT_IP]);
                $this->log("Use IP: " . BOT_IP, Log\Logger::DEBUG);
            }
            // pcntl
            $this->pcntl = new \MKraemer\ReactPCNTL\PCNTL($this->loop);
            $this->pcntl->on(SIGTERM, function () {
                $this->stop('kill');
            });
            $this->pcntl->on(SIGINT, function () {
                $this->stop('by console');
            });
        }
        
        public function loginCotG() {
            $this->delete_cookies();
            $headers = array(
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Encoding: gzip, deflate',
                'Accept-Language: de-DE,de;q=0.8,en-US;q=0.6,en;q=0.4'
            );

            $force_debug = false;
            
            // curl
            $curl = new \KHR\React\Curl\Curl($this->loop);
            $curl->client->setMaxRequest(10);
            $curl->client->setSleep(10, 1.0, false);
            $curl->client->enableHeaders();
            $curl->client->setBaseUrl(BOT_HOME);
            $curl->client->setCurlOption([
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
                CURLOPT_DNS_USE_GLOBAL_CACHE => 0,
                //CURLOPT_DNS_SERVERS => '8.8.8.8,8.8.8.4',
                //CURLOPT_DNS_CACHE_TIMEOUT => 3600,
                CURLOPT_AUTOREFERER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_COOKIESESSION => true,
                CURLOPT_VERBOSE => $this->debug,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_ENCODING => 'gzip,deflate',
                CURLOPT_USERAGENT => USER_AGENT,
                ]);

            if (defined('BOT_IP')) {
                $curl->client->setCurlOption([CURLOPT_INTERFACE => BOT_IP]);
                $this->log("Use IP: " . BOT_IP, Log\Logger::DEBUG);
            }
            $this->log("Open URL:" . BOT_HOME . "/home.php", Log\Logger::DEBUG);
            $curl->get('/home.php', [CURLOPT_VERBOSE => $force_debug,CURLOPT_HTTPHEADER => $headers,CURLOPT_REFERER => BOT_HOME . '/'])->then(function (\KHR\React\Curl\Result $result) use($curl,$force_debug) {
                $body = $result->getBody();

                $cookies = [];
                if (!empty($body) && !$result->hasError()) {
                    $_info = $result->getInfo();
                    $path = parse_url($_info['url'], PHP_URL_PATH) ?: '/';
                    $queryString = parse_url($_info['url'], PHP_URL_QUERY);
                    if($path == '/home.php' && $queryString == '') {
                        if ($this->debug) $this->log("GotG get Homepage!", Log\Logger::DEBUG);
                        $_html = str_get_html($body);
                        $_frmh = $_html->getElementById('frmh')->getAttribute('value');
                        if ($_frmh != '') {
                            if ($this->debug) $this->log("Found frmh: " . $_frmh, Log\Logger::DEBUG);
                        } else {
                            if ($this->debug) $this->log("No frmh found!", Log\Logger::DEBUG);
                            exit;
                        }
                        _destroy($_html);
                        
                        $_cookies = @$result->headers['set-cookie'];

                        if (preg_match_all("/document\.cookie = \"(qrv=.*)\";$/m", $body, $_match)) $_cookies[] = $_match[1][0];
                    
                        $cookies = $this->process_cookies($_cookies);
                        if ($this->debug) $this->log("CotG cookies: " . print_r($cookies, true), Log\Logger::DEBUG);
                        
                        //key by CotG 1.937
                        $_ppp = $this->get_encrypted($_frmh, '2sfSDD2355adcd');
                        
                        $params = array(
                            'email'     => BOT_EMAIL,
                            'remember'  => 1,
                            'frmh'      => $_frmh,
                            'p'         => hash('sha512', BOT_PASSWORD),
                            'pp'        => BOT_PASSWORD,
                            'ppp'       => $_ppp,
                            'info'      => USER_AGENT_INFO,
                            'str'       => USER_AGENT_GUID
                        );
                        
                        $headers = array(
                            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                            'Accept-Encoding: gzip, deflate',
                            'Accept-Language: de-DE,de;q=0.8,en-US;q=0.6,en;q=0.4',
                            'Cache-Control: no-cache',
                            'Connection: keep-alive',
                            //'Content-Type: application/x-www-form-urlencoded',
                            'Host: ' . parse_url(BOT_HOME, PHP_URL_HOST),
                            //'Origin: ' . BOT_HOME,
                            'Pragma: no-cache',
                            'Upgrade-Insecure-Requests: 1',
                            'Referer: ' . BOT_HOME . '/home.php'
                        );
                          
                        $_cookie_array = [];
                        foreach ($cookies as $cookie_obj => $cookie_params) 
                            $_cookie_array[] = $cookie_obj . '=' . $cookie_params['value'];    

                        if (!empty($params)) foreach ($params as $key => &$val) {
                            // encoding to JSON array fields, for example reply_markup
                            if (!is_numeric($val) && !is_string($val)) {
                              $val = Json\Json::Encode($val);
                            } 
                        }

                        $this->log("CotG login", Log\Logger::DEBUG);
                        $curl->post('/nincludes/pro_log.php', $params, [CURLOPT_VERBOSE => $force_debug,CURLOPT_HTTPHEADER => $headers,CURLOPT_REFERER => BOT_HOME . '/home.php',CURLOPT_COOKIE => implode('; ', $_cookie_array)])->then(function (\KHR\React\Curl\Result $result) use($curl,$force_debug) {
                            $body = $result->getBody();

                            if (!empty($body) && !$result->hasError()) {
                                $_info = $result->getInfo();
                                $path = parse_url($_info['url'], PHP_URL_PATH) ?: '/';
                                $queryString = parse_url($_info['url'], PHP_URL_QUERY);
                                if($path == '/home.php' && $queryString == '') {
                                    if ($this->debug) $this->log("GotG login done!", Log\Logger::DEBUG);
                                    $_cookies = @$result->headers['set-cookie'];
                                    $cookies = $this->process_cookies($_cookies, array('qrv'));

                                    $params = array(
                                        'w'     => 'World00'
                                    );
                                    
                                    $_cookie_array = [];
                                    foreach ($cookies as $cookie_obj => $cookie_params) 
                                        $_cookie_array[] = $cookie_obj . '=' . $cookie_params['value'];    

                                    if (!empty($params)) foreach ($params as $key => &$val) {
                                        // encoding to JSON array fields, for example reply_markup
                                        if (!is_numeric($val) && !is_string($val)) {
                                          $val = Json\Json::Encode($val);
                                        } 
                                    }
                                    
                                    $headers = array(
                                        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                                        'Accept-Encoding: gzip, deflate',
                                        'Accept-Language: de-DE,de;q=0.8,en-US;q=0.6,en;q=0.4',
                                        'Cache-Control: no-cache',
                                        'Connection: keep-alive',
                                        //'Content-Type: application/x-www-form-urlencoded',
                                        'Host: ' . parse_url(BOT_SERVER, PHP_URL_HOST),
                                        //'Origin: ' . BOT_HOME,
                                        'Pragma: no-cache',
                                        'Upgrade-Insecure-Requests: 1',
                                        'Referer: ' . BOT_HOME . '/home.php'
                                    );
                        
                                    $curl->client->setBaseUrl(BOT_SERVER);
                                    $this->log("Load world", Log\Logger::DEBUG);
                                    $curl->post('/wload.php', $params, [CURLOPT_VERBOSE => $force_debug,CURLOPT_HTTPHEADER => $headers,CURLOPT_REFERER => BOT_HOME . '/home.php',CURLOPT_COOKIE => implode('; ', $_cookie_array)])->then(function (\KHR\React\Curl\Result $result) use($curl,$force_debug) {
                                        $body = $result->getBody();

                                        if (!empty($body) && !$result->hasError()) {
                                            $_info = $result->getInfo();
                                            $path = parse_url($_info['url'], PHP_URL_PATH) ?: '/';
                                            $queryString = parse_url($_info['url'], PHP_URL_QUERY);
                                            if($path != '' && $queryString == '') {
                                                if ($this->debug) $this->log("Load world done!", Log\Logger::DEBUG);
                                                $_cookies = @$result->headers['set-cookie'];
                                                $cookies = $this->process_cookies($_cookies);
                                                // DONE grab needl!!!
                                                if (preg_match_all("/var ppss=(\d{1,});/m", $body, $_match)) {
                                                    $_ppss = $_match[1][0];
                                                    if ($this->debug) $this->log("Found ppss: " . $_ppss, Log\Logger::DEBUG);
                                                    $this->ppss = $_ppss;
                                                } else {
                                                    if ($this->debug) $this->log("No ppss found!", Log\Logger::DEBUG);
                                                    exit;
                                                }
                                                if (preg_match_all("/var tpnslu='(\w{5,})';/m", $body, $_match)) {
                                                    $_tpnslu = $_match[1][0];
                                                    if ($this->debug) $this->log("Found tpnslu: " . $_tpnslu, Log\Logger::DEBUG);
                                                    $this->tpnslu = $_tpnslu;
                                                } else {
                                                    if ($this->debug) $this->log("No tpnslu found!", Log\Logger::DEBUG);
                                                    exit;
                                                }
                                                if (preg_match_all('/"alasstylesheet.css\?([^"]{1,})"/m', $body, $_match)) {
                                                    $_version = $_match[1][0];
                                                    if ($this->debug) $this->log("Found version: " . $_version, Log\Logger::DEBUG);
                                                    $this->version = $_version;
                                                } else {
                                                    if ($this->debug) $this->log("No version found!", Log\Logger::DEBUG);
                                                    exit;
                                                }
                                                // set Session_Id
                                                $this->session = $this->get_cookie('sec_session_id');
                                                
                                                $_cookie_array = [];
                                                foreach ($cookies as $cookie_obj => $cookie_params) 
                                                    $_cookie_array[] = $cookie_obj . '=' . $cookie_params['value'];
                                                    
                                                $headers = array(
                                                    'Accept: */*',
                                                    'Accept-Encoding: gzip, deflate',
                                                    'Accept-Language: de-DE,de;q=0.8,en-US;q=0.6,en;q=0.4',
                                                    'Cache-Control: no-cache',
                                                    'Connection: keep-alive',
                                                    'Host: ' . parse_url(BOT_SERVER, PHP_URL_HOST),
                                                    'Pragma: no-cache',
                                                    //'Upgrade-Insecure-Requests: 1',
                                                    'Referer: ' . BOT_SERVER . '/',
                                                    "Origin: " . BOT_SERVER, 
                                                    "Content-Type: application/x-www-form-urlencoded; charset=UTF-8",
                                                    "Content-Encoding: undefined",
                                                    "X-Requested-With: XMLHttpRequest",
                                                    "pp-ss: " . $_ppss,
                                                );
                                                
                                                $curl->get('/includes/ServerDate.php?v=' . $this->version . '?time=now', [CURLOPT_VERBOSE => $force_debug,CURLOPT_HTTPHEADER => $headers,CURLOPT_REFERER => BOT_SERVER . '/',CURLOPT_COOKIE => implode('; ', $_cookie_array)])->then(
                                                    function (\KHR\React\Curl\Result $result) use($curl, $force_debug, $_cookie_array, $headers) {
                                                        $body = $result->getBody();
                                                        if (!empty($body) && !$result->hasError()) {
                                                            // DONE grab needl!!!
                                                            if (preg_match_all("/\((\d{13})\);/m", $body, $_match)) {
                                                                $_time = (int) $_match[1][0];
                                                                $_local_time = $this->get_server_time();
                                                                if ($this->debug) $this->log("Found server time: " . $_time, Log\Logger::DEBUG);
                                                                $_offset = ($_local_time - $_time) * -1;
                                                                if ($this->debug) $this->log("Has offset: " . sprintf("%+d", $_offset), Log\Logger::DEBUG);
                                                                $this->offset = $_offset;
                                                            } else {
                                                                if ($this->debug) $this->log("No offset found!", Log\Logger::WARN);
                                                            }
                                                            
                                                            //todo: move all after this line into ServerDate
                                                            $params_pL = array(
                                                                'type' => 1
                                                            );
                                                            
                                                            $curl->post('/includes/pL.php', $params_pL, [CURLOPT_VERBOSE => $force_debug,CURLOPT_HTTPHEADER => $headers,CURLOPT_REFERER => BOT_SERVER . '/',CURLOPT_COOKIE => implode('; ', $_cookie_array)])->then(
                                                                function (\KHR\React\Curl\Result $result) use($curl, $force_debug, $_cookie_array, $headers) {
                                                                    $this->log("Successful send alive data!", Log\Logger::NOTICE);
                                                                    _destroy($result);
                                                                },
                                                                function (Exception $e) {
                                                                    if ($this->debug) $this->log($e->getMessage(), Log\Logger::DEBUG);
                                                                }
                                                            );

                                                            _destroy($params_pL);
                                                            
                                                            // key by CotG 1.937
                                                            $_local_time = $this->get_server_time();
                                                            $this->log('Local time: ' . $_local_time);
                                                            $_a = $this->get_encrypted($_local_time, '1QA64sa23511sJx1e2');
                                                            
                                                            $params_pD = array(
                                                                'a'     => $_a
                                                            );
                                                            
                                                            // get Player Info
                                                            $curl->post('/includes/pD.php', $params_pD, [CURLOPT_VERBOSE => $force_debug,CURLOPT_HTTPHEADER => $headers,CURLOPT_REFERER => BOT_SERVER . '/',CURLOPT_COOKIE => implode('; ', $_cookie_array)])->then(
                                                                function (\KHR\React\Curl\Result $result) use($curl, $force_debug, $_a, $_cookie_array, $headers) {
                                                                    $data = $result->getBody();
                                                                    if ($this->debug) $this->log("CotG data: " . print_r($data, true), Log\Logger::DEBUG);
                                                                    if (!empty($data) && !$result->hasError()) {
                                                                        $json = Json\Json::Decode($data);
                                                                        if ($json->{'_id'}) {
                                                                            $this->log("Successful receive bot data!", Log\Logger::NOTICE);
                                                                            //if ($this->debug) print_r($json);
                                                                            if ($this->debug) $this->log("Has bot owner: " . $this->owner, Log\Logger::DEBUG);
                                                                            $this->bot_user_name = $json->{'pn'};
                                                                            if ($this->debug) $this->log("Found bot name: " . $this->bot_user_name, Log\Logger::DEBUG);
                                                                            $this->bot_user_id = $json->{'pid'};
                                                                            if ($this->debug) $this->log("Found bot id: " . $this->bot_user_id, Log\Logger::DEBUG);
                                                                            if (defined('BOT_ALLY_ID')) {
                                                                                $this->ally_name = $json->{'planame'};
                                                                                                                                                                        
                                                                                                                                      
                                                                                if ($this->debug) $this->log("Found bot ally_name: " . $this->ally_name, Log\Logger::DEBUG);
                                                                                $this->ally_id = $this->get_bot_alliance_id();
                                                                                if ($this->debug) $this->log("Found bot ally_id: " . $this->ally_id, Log\Logger::DEBUG);
                                                                                if (defined('BOT_SISTER_ID')) {
                                                                                    $this->sister_id = BOT_SISTER_ID;
                                                                                    if ($this->debug) $this->log("Found bot sister_ids: " . $this->sister_id, Log\Logger::DEBUG);
                                                                                }
                                                                            }
                                                                            $this->bot_last_city = $json->{'lcit'};
                                                                            if ($this->debug) $this->log("Found bot last_city: " . $this->bot_last_city, Log\Logger::DEBUG);
                                                            
                                                                            $this->start();
                                                                        } else {
                                                                            $this->log("Invalid bot data!", Log\Logger::ERR);
                                                                            exit;
                                                                        }
                                                                    } else {
                                                                        $this->log("No data received ({$_a})", Log\Logger::WARN);
                                                                        if ($this->debug) $this->log("Has bot owner: " . $this->owner, Log\Logger::DEBUG);
                                                                        $this->bot_user_name = BOT_USER_NAME;
                                                                        if ($this->debug) $this->log("Set bot name: " . $this->bot_user_name, Log\Logger::DEBUG);
                                                                        $this->bot_user_id = BOT_USER_ID;
                                                                        if ($this->debug) $this->log("Set bot id: " . $this->bot_user_id, Log\Logger::DEBUG);
                                                                        if (defined('BOT_ALLY_ID')) {
                                                                                                                                                          
                                                                                                                                                                  
                                                                            $this->ally_name = BOT_ALLY_NAME;
                                                                            if ($this->debug) $this->log("Set bot ally_name: " . $this->ally_name, Log\Logger::DEBUG);
                                                                            $this->ally_id = $this->get_bot_alliance_id();//BOT_ALLY_ID;//$json->{'paid'};
                                                                            if ($this->debug) $this->log("Set bot ally_id: " . $this->ally_id, Log\Logger::DEBUG);
                                                                            if (defined('BOT_SISTER_ID')) {
                                                                                $this->sister_id = BOT_SISTER_ID;
                                                                                if ($this->debug) $this->log("Set bot sister_ids: " . $this->sister_id, Log\Logger::DEBUG);
                                                                            }
                                                                        }
                                                                        $this->bot_last_city = BOT_LAST_CID;
                                                                        if ($this->debug) $this->log("Set bot last_city: " . $this->bot_last_city, Log\Logger::DEBUG);
                                                                       
                                                                        $this->start();
                                                                               
                                                                    }
                                                                    _destroy($json, $data, $result);
                                                                },
                                                                function (Exception $e) {
                                                                    $this->log("Error receive bot data!", Log\Logger::ERR);
                                                                    if ($this->debug) $this->log($e->getMessage(), Log\Logger::DEBUG);
                                                                });
                                                            _destroy($data, $result, $headers, $_info, $queryString, $path, $cookies, $_cookies, $_cookie_array, $crypt_js, $v8, $_a, $params_pD);
                                                        }
                                                        _destroy($result, $body);
                                                    },
                                                    function (Exception $e) {
                                                        if ($this->debug) $this->log($e->getMessage(), Log\Logger::DEBUG);
                                                    }
                                                );

                                            } else if ($queryString != '') {
                                                $this->log("Error code received - " . $queryString, Log\Logger::WARN);
                                                exit;
                                            } else {
                                                $this->log("Wrong path - " . $path, Log\Logger::WARN);
                                                exit;
                                            }
                                        } else {
                                            $this->log("No data received - " . $result->error . ' (' . $result->errorCode . ')', Log\Logger::WARN);
                                            exit;
                                        }
                                    },
                                    function (Exception $e) {
                                        $this->log("Error Load world!", Log\Logger::ERR);
                                        if ($this->debug) $this->log($e->getMessage(), Log\Logger::DEBUG);
                                    });
                                    _destroy($body, $result);
                                    
                                } else if ($queryString != '') {
                                    $this->log("Error code received - " . $queryString, Log\Logger::WARN);
                                    exit;
                                } else {
                                    $this->log("Wrong path - " . $path, Log\Logger::WARN);
                                    exit;
                                }
                            } else {
                                $this->log("No data received - " . $result->error . ' (' . $result->errorCode . ')', Log\Logger::WARN);
                                exit;
                            }
                        },
                        function (Exception $e) {
                            $this->log("Error GotG login!", Log\Logger::ERR);
                            if ($this->debug) $this->log($e->getMessage(), Log\Logger::DEBUG);
                        });
                        _destroy($body, $result, $params, $key, $val, $_cookie_array, $headers, $_ppp, $v8, $crypt_js, $_cookies, $cookies, $queryString, $path, $_info);
                    } else if ($queryString != '') {
                        $this->log("Error code received - " . $queryString, Log\Logger::WARN);
                        exit;
                    } else {
                        $this->log("Wrong path - " . $path, Log\Logger::WARN);
                        exit;
                    }

                } else {
                    $this->log("No data received - " . $result->error . ' (' . $result->errorCode . ')', Log\Logger::WARN);
                    exit;
                }
            },
            function (Exception $e) {
                $this->log("Error GotG Homepage!", Log\Logger::ERR);
                if ($this->debug) $this->log($e->getMessage(), Log\Logger::DEBUG);
            });
            _destroy($curl, $headers, $body, $result);
        }
        
        public function process_cookies($_cookies_add, $_cookies_remove = null) {
            $cookies = [];
            if (is_array($_cookies_add)) foreach($_cookies_add as $_cookie) {
                $_cookie_params = explode(';', $_cookie);
                $_param_first = true;
                $_cookie_object = [];
                if (is_array($_cookie_params)) foreach($_cookie_params as $_cookie_param) {
                    $_param = explode('=', trim($_cookie_param), 2);
                    if (count($_param) == 2)
                        list($_name, $_value) = $_param;
                    else
                        $_name = trim($_cookie_param);
                    
                    if ($_param_first) {
                        $_param_first = false;
                        $_cookie_object = array(
                            'name' => $_name,
                            'value'=> $_value,
                        );
                    } else {
                        switch(strtolower($_name)) {
                            case 'secure':
                            case 'httponly':
                                $_cookie_object[strtolower($_name)] = true;
                                break;
                            case 'max-age':
                            case 'expires':
                            case 'domain':
                            case 'path':
                                $_cookie_object[strtolower($_name)] = $_value;
                                break;
                        }
                    }
                }
                $this->set_cookie($_cookie_object);
            }
            if (is_array($_cookies_remove)) foreach($_cookies_remove as $_cookie) {
                $this->delete_cookie($_cookie);
            }
            $_dbCookies = $this->get_cookies();
            foreach ($_dbCookies as $_name => $_value) {
                $cookies[$_name] = array('name' => $_name, 'value' => $_value);
            }
            _destroy($_cookies_add, $_cookies_remove, $_cookie_params, $_param_first, $_cookie_object, $_dbCookies);
            return $cookies;
        }
        
        public function call($url, $params, $type, $debug = null) {
            $url = DIRECTORY_SEPARATOR . $url;
            $_cookie_array = [];
            $_dbCookies = $this->get_cookies();
            foreach ($_dbCookies as $_name => $_value) {
                $_cookie_array[] = $_name . '=' . $_value;
            }
            $force_debug = ($debug !== null) ? $debug : $this->debug;
            $this->log("Call {$type} with URL:{$url} DATA:" . Json\Json::Encode($params), Log\Logger::DEBUG);
            $this->curl->post($url, $params, [CURLOPT_VERBOSE => $force_debug,CURLOPT_COOKIE => implode('; ', $_cookie_array)])->then(function (\KHR\React\Curl\Result $result) use($type) {
                $data = $result->getBody();

                if (!empty($data)) {
                    if ($this->debug) $this->log("Got {$type} data!", Log\Logger::DEBUG);

                    $this->analyser->using($data, $type, $this->debug);
                } else {
                    $this->log("No {$type} data received!", Log\Logger::WARN);
                }
                _destroy($data, $result);
            });
            _destroy($url, $params, $type, $_cookie_array, $_dbCookies, $url);
        }
        
        public function addCall($url, $params, $type) {
            $url = DIRECTORY_SEPARATOR . $url;
            $_cookie_array = [];
            $_dbCookies = $this->get_cookies();
            foreach ($_dbCookies as $_name => $_value) {
                $_cookie_array[] = $_name . '=' . $_value;
            }
            
            $this->log("addCall {$type} with URL:{$url} DATA:" . Json\Json::Encode($params), Log\Logger::DEBUG);
            $opts[CURLOPT_URL] = $url;
            $opts[CURLOPT_POST] = true;
            if (defined('BOT_IP')) {
                $opts[CURLOPT_INTERFACE] = BOT_IP;
                $this->log("Use IP: " . BOT_IP, Log\Logger::DEBUG);
            }
            $opts[CURLOPT_COOKIE] = implode('; ', $_cookie_array);
            $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
            $this->curl->add($opts, ['id' => $type])->then(function (\KHR\React\Curl\Result $result) use($type) {
                $data = $result->getBody();

                if (!empty($data)) {
                    if ($this->debug) $this->log("Got {$type} data!", Log\Logger::DEBUG);

                    $this->analyser->using($data, $type, $this->debug);
                } else {
                    $this->log("No {$type} data received!", Log\Logger::WARN);
                }
                _destroy($data, $result);
            });
            _destroy($url, $params, $type, $opts, $_cookie_array, $_dbCookies, $url);
        }
        
        public function multiCall() {
            $this->log("Run multiCall with {$this->curl->client->getCountQuery()} calls.", Log\Logger::DEBUG);
            $this->curl->run();
        }
        
        // later see@ https://api.telegram.org/bot192068441:AAFtGxBet3SEQgkIXhdoN8DFl6XS7wDNKhk/getUpdates
        public function telegram($message) {
            if (!defined('TELEGRAM_TOKEN')) return;
            $headers = array(
                'User-Agent' => USER_AGENT,
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => '*/*',
                'Accept-Encoding' => 'gzip, deflate',
                'Accept-Language' => 'de-DE,de;q=0.8,en-US;q=0.6,en;q=0.4',
                'Content-Type' => 'application/x-www-form-urlencoded; charset=UTF-8',
            );

            $url = "https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/sendMessage";
            //$message = preg_replace("/\\\\u([0-9a-fA-F]{4})/e", "iconv('UCS-4LE','UTF-8',pack('V', hexdec('U$1')))", $message);
            $message = preg_replace_callback(   // php5.x preg_replace_callback() method
                '/\\\\u([0-9a-fA-F]{4})/',
                function ($m) {
                    return iconv('UCS-4LE','UTF-8', pack('V', hexdec("U{$m[1]}")));
                },
                $message);
            
            $params = array('chat_id' => TELEGRAM_CHANNEL, 'text' => $message, 'parse_mode' => 'HTML');
            if (!empty($params)) foreach ($params as $key => &$val) {
                // encoding to JSON array fields, for example reply_markup
                if (!is_numeric($val) && !is_string($val)) {
                  $val = Json\Json::Encode($val);
                }
            }
            $this->browser->submit($url, $params, $headers)->then(function (\Clue\React\Buzz\Message\Response $result) {
                $data = $result->getBody();
                if (!empty($data)) {
                    $json = Json\Json::Decode($data);
                    if ($json->{'ok'}) $this->log("Successful send telegram!", Log\Logger::NOTICE);
                    else $this->log("Invalid telegram token!", Log\Logger::ERR);

                } else {
                    $this->log("No response from telegram received!", Log\Logger::WARN);
                }
                _destroy($data, $result);
            });
            _destroy($message, $headers, $url, $params, $val);
        }
        
        public function __destruct() {
            $this->state = HALT;
            $this->log("Exit!");
            $this->cron     = null;
            $this->chat     = null;
            $this->loop     = null;
            $this->lock     = null;
            $this->client   = null;
            $this->cross    = null;
            $this->logger   = null;
            $this->browser  = null;
        }
        
        public function update(\SplSubject $subject) {
            global $redis;
            
            while ($this->stop) {
                $this->log("Wait for reload!", Log\Logger::NOTICE);
                usleep(25 * 1000);
            }
            $input = $subject->note;
            
            switch ($input['type']) {
                case CHAT:
                $this->debug("Fire" . ucfirst(strtolower($input['type'])) . "Hooks ({$input['channel']})");
                $hooks = @$this->hooks[$input['channel']];
                if (is_array($hooks)) foreach ($hooks as $hook) {
                    $this->debug("Found " . ucfirst(strtolower($input['type'])) . " Hook '{$hook->getName()}' ({$input['channel']})");
                    if ($hook->compCommand($input)) {
                        $hook->callFunction($this, $input);
                        if ($hook->breakThis()) break;
                    }
                }
                break;
                case STATISTICS:
                case MILITARY:
                case ALLIANCE:
                case SYSTEMIN:
                case SHRINES:
                case SYSTEM:
                case DATA:
                case USER:
                case POLL:
                $this->debug("Fire" . ucfirst(strtolower($input['type'])) . "Hooks ({$input['id']})");
                $hooks = @$this->hooks[$input['type']];
                if (is_array($hooks)) foreach ($hooks as $hook) {
                    $hook->callFunction($this, $input);
                    if ($hook->breakThis()) break;
                }
                break;
                case BOT:
                $this->debug("Fire" . ucfirst(strtolower($input['type'])) . "Hooks ({$input['name']})");
                $hooks = @$this->hooks[$input['type']];
                if (is_array($hooks)) foreach ($hooks as $hook) {
                    $hook->callFunction($this, $input);
                    if ($hook->breakThis()) break;
                }
                break;
                case CRON:
                case TICK:
                $this->debug("Fire" . ucfirst(strtolower($input['type'])) . "Events ({$input['name']})");
                $events = @$this->events[$input['name']];
                if (is_array($events)) {
                    sort($events);
                    foreach ($events as $_key => $event) {
                        if ($event instanceof Plugins\Hook) {
                            $event->callFunction($this, $input);
                            if ($event->breakThis()) break;
                        }
                    }
                }
                break;
            }
            _destroy($input, $events, $event, $hooks, $hook, $subject, $_key);
        }
        
        // implement magic logging
        public function __call($method, $args) {
            if ($this->logger && method_exists($this->logger, $method)) return call_user_func_array(array(&$this->logger, $method), $args);
            return true;
        }
        
        // shorthand for log
        public function log($message, $priority = Log\Logger::INFO) {

            if (isset($this->last_message[$priority]) && $message == @$this->last_message[$priority]['message']) {
                $this->last_message[$priority]['count'] ++;
                return;
            } else if (isset($this->last_message[$priority]) && @$this->last_message[$priority]['count'] >= 1) {
                $this->logger->log($this->last_message[$priority]['priority'], $this->last_message[$priority]['message'] . " <-repeated {$this->last_message[$priority]['count']} times");
            }
            
            $this->last_message[$priority]['priority'] = $priority;
            $this->last_message[$priority]['message'] = $message;
            $this->last_message[$priority]['count'] = 0;
            $this->logger->log($priority, $message);
            _destroy($message, $priority);
        }
        
        // shorthand for debug
        public function debug($message) {
            if ($this->debug) {
                $this->log($message, Log\Logger::DEBUG);
            }
            _destroy($message);
        }
        
        // shorthand for error
        public function error($message) {
            $this->log($message, Log\Logger::ERR);
            _destroy($message);
        }
        
        public function run() {
            $this->log("PHP " . phpversion());
            $this->log("Start!");
            $this->log("Debug: " . (($this->debug) ? 'true' : 'false'), Log\Logger::NOTICE);
            $this->log("IgnoreGlobalChat: " . ((IGNORE_GLOBALIN) ? 'true' : 'false'), Log\Logger::NOTICE);
            $this->log("World: " . BOT_WORLD, Log\Logger::NOTICE);
            $this->add_category('default', array('humanice' => true), PUBLICY);
            $this->offset = 0;
            $this->loginCotG();
            // start loop!
            $this->state = STARTUP;
            $this->loop->run();
        }
        
        public function start() {
            $this->log("LoadHooks!");
            $this->load_hooks();
            $this->log("Start chat!");
            $this->client->open();
            if (defined('BOT_CROSS')) {
                $this->cross_key = $this->set_user_hash($this->bot_user_name);
                $this->log("Start cross!");
                $this->cross->open();
            }
            if (defined('BOT_WEB_PORT')) $this->socket->listen(BOT_WEB_PORT);
            if (defined('BOT_SOCKET')) $this->server->bind();
        }
        
        public function stop($reason) {
            $this->log("Stop ($reason)!");
            if (function_exists('memprof_enable') && memprof_enabled()) {
                memprof_dump_callgrind(fopen(HEAP_FILE . BOT_NAME, 'w'));
            }
            if (defined('BOT_CROSS') && $this->is_cross_connected) $this->cross->close();
            if ($this->is_connected) $this->client->close();
            else $this->loop->stop();
            exit;
        }
        
        public function add_category($category, $rules = [], $access = PUBLICY) {
            if (!is_object(@$this->categories[md5(strtoupper($category))])) $this->categories[md5(strtoupper($category))] = Plugins\Category::factory($category, $rules, $access);
            _destroy($category, $rules, $access);
        }
        
        public function set_cookie($cookie) {
            $this->add_cookie($cookie['name'], $cookie['value'], ((!empty($cookie['max-age'])) ? $cookie['max-age'] : 0));
            _destroy($cookie);
        }
             
        public function add_cookie($name, $value, $ttl = 0) {
            global $redis;
            
            $cookie_key = 'cookies:' . BOT_EMAIL . ':';
            if (intval($ttl) >= 1) {
                return $redis->setEx($cookie_key . $name, $ttl, $value);
            } else {
                return $redis->set($cookie_key . $name, $value);
            }
        }
        
        public function get_cookies() {
            global $redis;
            
            $cookies = [];
            $cookie_key = 'cookies:' . BOT_EMAIL . ':';
            $cookie_keys = $redis->clearKey($redis->Keys("{$cookie_key}*"), "/{$cookie_key}/");
            if (!empty($cookie_keys)) foreach($cookie_keys as $_id => $cookie_name) {
                    if ($cookie_value = $redis->get("{$cookie_key}{$cookie_name}")) $cookies[$cookie_name] = $cookie_value;
            }
            _destroy($cookie_key, $cookie_keys, $cookie_name);
            return $cookies;
        }
        
        public function get_cookie($name) {
            global $redis;
            
            $cookie = [];
            $cookie_key = 'cookies:' . BOT_EMAIL . ':';
            return $redis->get("{$cookie_key}{$name}");
        }
        
        public function delete_cookies() {
            global $redis;
            
            $cookies = [];
            $cookie_key = 'cookies:' . BOT_EMAIL . ':';
            $cookie_keys = $redis->clearKey($redis->Keys("{$cookie_key}*"), "/{$cookie_key}/");
            if (!empty($cookie_keys)) foreach($cookie_keys as $_id => $cookie_name) {
                    $redis->del("{$cookie_key}{$cookie_name}");
            }
            _destroy($cookie_key, $cookie_keys, $cookies, $cookie_name);
        }
        
        public function delete_cookie($name) {
            global $redis;
            
            $cookie = [];
            $cookie_key = 'cookies:' . BOT_EMAIL . ':';
            return $redis->del("{$cookie_key}{$name}");
        }
        
        private function load_hooks($reload = false) {
            $dirh = opendir(FNC_DATA);
            while ($file = readdir($dirh)) {
                $_file = pathinfo($file);
                if ($_file['extension'] == "php") {
                    if ($reload) $this->log("Reload hooks: " . $_file['filename']); else $this->log("Load hooks: " . $_file['filename']);
                    /** @noinspection PhpIncludeInspection */
                    $ret = include FNC_DATA . $_file['basename'];
                    if (!$ret) $this->log("Hooks disabled from: " . $_file['filename']);
                }
            }
            closedir($dirh);
            _destroy($dirh, $file, $_file);
            return true;
        }
        
        public function add_privmsg_hook($command, $name, $is_command = false, $regex = '', $function, $category = 'default') {
            $this->hooks[PRIVATEIN][md5($name)] = Plugins\Hook::factory(trim($command), $name, $is_command, $regex, $function, $this->get_category($category));
            _destroy($command, $name, $is_command, $regex, $function, $category);
        }
        
        public function add_funmsg_hook($command, $name, $is_command = false, $regex = '', $function, $category = 'default') {
            $this->hooks[GAMEIN][md5($name)] = Plugins\Hook::factory(trim($command), $name, $is_command, $regex, $function, $this->get_category($category));
            _destroy($command, $name, $is_command, $regex, $function, $category);
        }
        
        public function get_category($category) {
            if (!is_object(@$this->categories[md5(strtoupper($category))])) $this->add_category($category);
            
            return $this->categories[md5(strtoupper($category))];
        }
        
        public function add_provmsg_hook($command, $name, $is_command = false, $regex = '', $function, $category = 'default') {
            $this->hooks[PRIVATEOUT][md5($name)] = Plugins\Hook::factory(trim($command), $name, $is_command, $regex, $function, $this->get_category($category));
            _destroy($command, $name, $is_command, $regex, $function, $category);
        }
        
        public function add_allymsg_hook($command, $name, $is_command = false, $regex = '', $function, $category = 'default') {
            $this->hooks[ALLYIN][md5($name)] = Plugins\Hook::factory(trim($command), $name, $is_command, $regex, $function, $this->get_category($category));
            _destroy($command, $name, $is_command, $regex, $function, $category);
        }
        
        public function add_offimsg_hook($command, $name, $is_command = false, $regex = '', $function, $category = 'default') {
            $this->hooks[OFFICERIN][md5($name)] = Plugins\Hook::factory(trim($command), $name, $is_command, $regex, $function, $this->get_category($category));
            _destroy($command, $name, $is_command, $regex, $function, $category);
        }
        
        public function add_msg_hook($msg_hook, $command, $name, $is_command = false, $regex = '', $function, $category = 'default') {
            $_channels = array(
            OFFICERIN  => 'add_offimsg_hook',
            ALLYIN     => 'add_allymsg_hook',
            PRIVATEIN  => 'add_privmsg_hook',
            PRIVATEOUT => 'add_provmsg_hook',
            GAMEIN  => 'add_funmsg_hook'
            );
            if (is_array($msg_hook)) {
                foreach ($msg_hook as $msg) {
                    if (array_key_exists($msg, $_channels)) $this->{$_channels[$msg]}($command, $name, $is_command, $regex, $function, $category);
                }
            } else $this->{$_channels[$msg_hook]}($command, $name, $is_command, $regex, $function, $category);
            _destroy($msg_hook, $msg, $command, $name, $is_command, $regex, $function, $category, $_channels);
        }
        
        public function add_user_hook($command, $name, $function, $category = 'user') {
            $this->hooks[USER][md5($name)] = Plugins\Hook::factory(trim($command), $name, false, null, $function, $this->get_category($category));
            _destroy($command, $name, $function, $category);
        }
        
        public function add_bot_hook($command, $name, $function, $category = 'bot') {
            $this->hooks[BOT][md5($name)] = Plugins\Hook::factory(trim($command), $name, false, null, $function, $this->get_category($category));
            _destroy($command, $name, $function, $category);
        }
        
        public function add_statistic_hook($command, $name, $function, $category = 'statistic') {
            $this->hooks[STATISTICS][md5($name)] = Plugins\Hook::factory(trim($command), $name, false, null, $function, $this->get_category($category));
            _destroy($command, $name, $function, $category);
        }
        
        public function add_military_hook($command, $name, $function, $category = 'military') {
            $this->hooks[MILITARY][md5($name)] = Plugins\Hook::factory(trim($command), $name, false, null, $function, $this->get_category($category));
            _destroy($command, $name, $function, $category);
        }
        
        public function add_shrines_hook($command, $name, $function, $category = 'shrines') {
            $this->hooks[SHRINES][md5($name)] = Plugins\Hook::factory(trim($command), $name, false, null, $function, $this->get_category($category));
            _destroy($command, $name, $function, $category);
        }
        
        public function add_data_hook($command, $name, $function, $category = 'data') {
            $this->hooks[DATA][md5($name)] = Plugins\Hook::factory(trim($command), $name, false, null, $function, $this->get_category($category));
            _destroy($command, $name, $function, $category);
        }
        
        public function add_system_hook($command, $name, $function, $category = 'system') {
            $this->hooks[SYSTEMIN][md5($name)] = Plugins\Hook::factory(trim($command), $name, false, null, $function, $this->get_category($category));
            _destroy($command, $name, $function, $category);
        }
        
        public function add_poll_hook($command, $name, $function, $category = 'poll') {
            $this->hooks[POLL][md5($name)] = Plugins\Hook::factory(trim($command), $name, false, null, $function, $this->get_category($category));
            _destroy($command, $name, $function, $category);
        }
        
        public function add_alliance_hook($command, $name, $function, $category = 'alliance') {
            $this->hooks[ALLIANCE][md5($name)] = Plugins\Hook::factory(trim($command), $name, false, null, $function, $this->get_category($category));
            _destroy($command, $name, $function, $category);
        }
        
        public function add_tick_event($events, $command, $name, $function, $category = 'tick') {
            if (!is_array($events)) $events = array($events);
            foreach ($events as $event) {
                if (!empty($event)) $this->events[$event][md5($name)] = Plugins\Hook::factory(trim($command), $name, false, null, $function, $this->get_category($category));
            }
            _destroy($events, $event, $command, $name, $function, $category);
        }
        
        public function add_cron_event($events, $command, $name, $function, $category = 'cron') {
            if (!is_array($events)) $events = array($events);
            foreach ($events as $event) {
                if (!empty($event)) $this->events[$event][md5($name)] = Plugins\Hook::factory(trim($command), $name, false, null, $function, $this->get_category($category));
            }
            _destroy($events, $event, $command, $name, $function, $category);
        }
        
        public function call_event($input, $name = null) {
            global $redis;
            
            $this->debug("Call" . ucfirst(strtolower($input['type'])) . "Events ({$input['name']})" . ((!is_null($name)) ? " -> {$name}" : ''));
            $events = @$this->events[$input['name']];
            if (is_array($events)) {
                sort($events);
                foreach ($events as $event) {
                    if (is_null($name) || $name == $event->getName()) {
                        if ($event instanceof Plugins\Hook) {
                            $event->callFunction($this, $input);
                            if ($event->breakThis()) return;
                        }
                    }
                }
            }
            _destroy($events, $event, $input, $name);
        }
        
        public function call_hook($input, $name = null) {
            global $redis;
            
            if (isset($input['type'])) {
                $hooks = @$this->hooks[$input['type']];
                $this->debug("Call" . ucfirst(strtolower($input['type'])) . "Hooks ({$input['name']}:{$input['id']})" . ((!is_null($name)) ? " -> {$name}" : ''));
            } else if (isset($input['channel'])) {
                $hooks = @$this->hooks[$input['channel']];
                $this->debug("Fire" . ucfirst(strtolower($input['type'])) . "Hooks ({$input['channel']})" . ((!is_null($name)) ? " -> {$name}" : ''));
            }
            if (is_array($hooks)) foreach ($hooks as $hook) {
                if (is_null($name) || $name == $hook->getName()) {
                    $hook->callFunction($this, $input);
                    if ($hook->breakThis()) break;
                }
            }
            _destroy($hooks, $hook, $input, $name);
        }
        
        public function reply_msg($type, $message, $user = null) {
            switch ($type) {
                case PRIVATEIN:
                $this->add_privmsg($message, $user);
                break;
                case ALLYIN:
                $this->add_allymsg($message);
                break;
                case OFFICERIN:
                $this->add_offimsg($message);
                break;
                case GAMEIN:
                $this->add_funmsg($message);
                break;
            }
            _destroy($type, $message, $user);
        }
        
        public function add_privmsg($message, $user) {
            if ((string)$message != '' && $this->is_global_user($user)) {
                $msg = array(
                'a' => 1,
                'b' => "/w {$user} {$message}"
                //,
                //'c' => $this->bot_user_name
                );
                if ($enc_msg = Json\Json::Encode($msg)) {
                    $this->debug("Send PRIVATEOUT to {$user}: " . $enc_msg);
                    $this->push_message($enc_msg);
                }
            }
            _destroy($enc_msg, $msg, $message, $user);
        }
        
        public function add_funmsg($message) {
            if ((string)$message != '') {
                $msg = array(
                'a' => 5,
                'b' => $message,
                'c' => $this->bot_user_name,
                'd' => $this->ally_name
                );
                $this->send_message($msg);
            }
            _destroy($msg, $message);
        }
        
        public function add_tellmsg($message, $user) {
            if ((string)$message != '' && $this->is_global_user($user)) {
                $msg = array(
                'a' => 8,
                'b' => $message,
                'c' => $this->bot_user_name,
                'd' => $this->ally_name
                );
                $this->send_message($msg, $user);
            }
            _destroy($msg, $message, $user);
        }
        
        public function add_crossmsg($message) {
            if ((string)$message != '') {
                $msg = array(
                'a' => 6,
                'b' => $message,
                'c' => $this->bot_user_name,
                'd' => $this->ally_name
                );
                $this->send_message($msg);
            }
            _destroy($msg, $message);
        }
        
        public function cross_allymsg($message, $user) {
            if ((string)$message != '') {
                $msg = array(
                'a' => 9,
                'b' => $message,
                'c' => $user,
                'd' => $this->ally_name
                );
                $this->send_message($msg);
            }
            _destroy($msg, $message, $user);
        }
        
        public function add_leadmsg($message) {
            if ((string)$message != '') {
                $msg = array(
                'a' => 7,
                'b' => $message,
                'c' => $this->bot_user_name,
                'd' => $this->ally_name
                );
                $this->send_message($msg);
            }
            _destroy($msg, $message);
        }
        
        public function cross_offimsg($message, $user) {
            if ((string)$message != '') {
                $msg = array(
                'a' => 10,
                'b' => $message,
                'c' => $user,
                'd' => $this->ally_name
                );
                $this->send_message($msg);
            }
            _destroy($msg, $message, $user);
        }
        
        public function cross_sysmsg($type, $data) {
            if ((string)$data != '') {
                $msg = array(
                'a' => 0,
                'b' => strtoupper($type),
                'c' => $data,
                'd' => $this->ally_name
                );
                $this->send_message($msg);
            }
            _destroy($msg, $message, $user);
        }
        
        public function add_allymsg($message) {
            if ((string)$message != '') {
                $msg = array(
                'a' => 3, // 1=world, 2=whisper, 3=ally, 4=offi
                'b' => $message,
                'c' => $this->bot_user_name
                );
                if ($enc_msg = Json\Json::Encode($msg)) {
                    $this->debug("Send ALLYOUT message: " . $enc_msg);
                    $this->push_message($enc_msg);
                }
            }
            _destroy($enc_msg, $msg, $message);
        }
        
        public function add_offimsg($message) {
            if ((string)$message != '') {
                $msg = array(
                'a' => 4, // 1=world, 2=whisper, 3=ally, 4=offi
                'b' => $message,
                'c' => $this->bot_user_name
                );
                if ($enc_msg = Json\Json::Encode($msg)) {
                    $this->debug("Send OFFICEROUT message: " . $enc_msg);
                    $this->push_message($enc_msg);
                }
            }
            _destroy($enc_msg, $msg, $message);
        }
        
        public function send_message($message, $user = null) {
            if (!defined('BOT_SOCKET')) {
                if (defined('BOT_CROSS')) {
                    $this->log("Tunnel msg({$message['a']}) to cross socket: " . $message['b'] . ' (' . $message['c'] . ')', Log\Logger::DEBUG);
                    $this->cross->send(Json\Json::Encode($message));
                }
                _destroy($message, $user);
                return;
            }
            switch (intval($message['a'])) {
                case 0:
                    $this->log("Broadcasting sysmsg to authorized clients: " . $message['b'], Log\Logger::DEBUG);
                    switch ($message['b']) {
                        case 'INCOMINGS':
                        case 'INCOMINGS1H':
                        case 'OUTGOINGS':
                        case 'OUTGOINGS1H':
                            if (defined('BOT_SISTER_ID')) {
                                $this->push_lastmessage($message, $message['a'], $message['d'], $message['b']);
                                $rights = $this->get_alliance_rights_by_id($this->get_alliance_id($message['d']));
                                $_io_right = (isset($rights['io'])) ? $rights['io'] : \ROLES::OFFICER;
                                $_io1h_right = (isset($rights['io1h'])) ? $rights['io1h'] : \ROLES::OFFICER;
                            
                                foreach($this->server->getConnections() as $client) {
                                    if ($client->getAuth()) {
                                        $alliance_name = $client->getAllianceName();
                                        if ($alliance_name !== $message['d']) { // not same alliance _ALL_
                                            $user = $client->getUserName();
                                            if (defined('BOT_ALLY_ID') && $this->is_ally_user($user, defined('BOT_SISTER_ID'))) {
                                                $rank = $this->get_rank_by_id($client->getUserId());
                                                $_message = (array)$message;
                                                if (strtoupper($message['b']) == 'INCOMINGS1H') {
                                                    if ($rank <= $_io1h_right && !($rank <= $_io_right)) {
                                                        $_message['b'] = 'INCOMINGS';
                                                        $this->log("Cross sysmsg to {$user}: " . $message['b'], Log\Logger::DEBUG);
                                                        $client->sendString(Json\Json::Encode($_message));
                                                    }
                                                } elseif (strtoupper($message['b']) == 'OUTGOINGS1H') {
                                                    if ($rank <= $_io1h_right && !($rank <= $_io_right)) {
                                                        $_message['b'] = 'OUTGOINGS';
                                                        $this->log("Cross sysmsg to {$user}: " . $message['b'], Log\Logger::DEBUG);
                                                        $client->sendString(Json\Json::Encode($_message));
                                                    }
                                                } elseif ($rank <= $_io_right) {
                                                    $this->log("Cross sysmsg to {$user}: " . $message['b'], Log\Logger::DEBUG);
                                                    $client->sendString(Json\Json::Encode($_message));
                                                }
                                            }
                                        }
                                    }
                                }
                                _destroy($_message);
                            }
                            break;
                        case 'MEMBERS':
                            foreach($this->server->getConnections() as $client) {
                                if ($client->getAuth()) {
                                    $alliance_name = $client->getAllianceName();
                                    if ($alliance_name !== $message['d']) { // not same alliance _ALL_
                                        $user = $client->getUserName();
                                        if (defined('BOT_ALLY_ID') && $this->is_ally_user($user, defined('BOT_SISTER_ID'))) {
                                            $rank = $this->get_rank_by_id($client->getUserId());
                                            //if ($rank <= \ROLES::MEMBER) 
                                                $client->sendString(Json\Json::Encode($message));
                                        }
                                    }
                                }
                            }
                            break;
                        default:
                            foreach($this->server->getConnections() as $client) {
                                if ($client->getAuth()) {
                                    $alliance_name = $client->getAllianceName();
                                    if ($alliance_name !== $message['d']) { // not same alliance _ALL_
                                        $user = $client->getUserName();
                                        if (defined('BOT_ALLY_ID') && $this->is_ally_user($user, defined('BOT_SISTER_ID'))) {
                                            $client->sendString(Json\Json::Encode($message));
                                        }
                                    }
                                }
                            }
                    }
                    break;
                case 5:
                    $this->push_lastmessage($message, $message['a']);
                    $this->log("Broadcasting funmsg to all clients: " . $message['b'], Log\Logger::DEBUG);
                    foreach($this->server->getConnections() as $client) {
                        if ($client->getAuth()) {
                            $user = $client->getUserName();
                            if (!defined('BOT_ALLY_ID') || $this->is_ally_user($user, defined('BOT_SISTER_ID'))) {
                                $client->sendString(Json\Json::Encode($message));
                            }
                        }
                    }
                    break;
                case 6:
                    //deprecated
                    break;
                case 7:
                    //deprecated
                    break;
                case 8:
                    foreach($this->server->getConnections() as $client) {
                        if ($client->getAuth()) {
                            $user_name = $client->getUserName();
                            if ($user_name == $user) {
                                $this->log("Send tellmsg to {$user}: " . $message['b'], Log\Logger::DEBUG);
                                $client->sendString(Json\Json::Encode($message));
                            }
                        }
                    }
                    break;
                case 9:
                    $this->push_lastmessage($message, $message['a'], $message['d']);
                    foreach($this->server->getConnections() as $client) {
                        if ($client->getAuth()) {
                            $alliance_name = $client->getAllianceName();
                            if ($alliance_name !== $message['d']) { // not same alliance
                                $user = $client->getUserName();
                                if (defined('BOT_ALLY_ID') && $this->is_ally_user($user, defined('BOT_SISTER_ID'))) {
                                    $this->log("Cross allymsg to {$user}: " . $message['b'], Log\Logger::DEBUG);
                                    $client->sendString(Json\Json::Encode($message));
                                }
                            }
                        }
                    }
                    break;
                case 10:
                    $this->push_lastmessage($message, $message['a'], $message['d']);
                    foreach($this->server->getConnections() as $client) {
                        if ($client->getAuth()) {
                            $alliance_name = $client->getAllianceName();
                            if ($alliance_name !== $message['d']) { // not same alliance
                                $user = $client->getUserName();
                                if (defined('BOT_ALLY_ID') && $this->has_rank($user, \ROLES::OFFICER, defined('BOT_SISTER_ID'))) {
                                    $this->log("Cross offimsg to {$user}: " . $message['b'], Log\Logger::DEBUG);
                                    $client->sendString(Json\Json::Encode($message));
                                }
                            }
                        }
                    }
                    break;
            }
            _destroy($message, $user, $alliance_name, $client);
        }
        
        private function push_message($message) {
            array_push($this->messages, trim($message));
            _destroy($message);
        }
        
        private function get_message() {
            return array_shift($this->messages);
        }
        
        private function unshift_message($message) {
            array_unshift($this->messages, trim($message));
            _destroy($message);
        }
        
        private function push_lastmessage($message, $channel, $alliance = '_ALL_', $type = 'TEXT') {
            global $redis;
            
            if ($redis->status()) {
                $alliance_id = ($alliance == '_ALL_') ? 0 : $this->get_alliance_id($alliance);
                $message_key = "messages:{$channel}:alliance:{$alliance_id}:{$type}";
                $redis->rPush($message_key, Json\Json::Encode($message));
                $redis->listTrim($message_key, (0 - MAX_LASTMESSAGES), -1);
            }
            _destroy($message, $channel, $alliance, $type);
        }
        
        private function get_lastmessages($channel, $alliance = '_ALL_', $type = 'TEXT', $count = MAX_LASTMESSAGES) {
            global $redis;
            
            if (!$redis->status()) return [];
            $alliance_id = ($alliance == '_ALL_') ? 0 : $this->get_alliance_id($alliance);
            $message_key = "messages:{$channel}:alliance:{$alliance_id}:{$type}";
            _destroy($channel, $alliance, $type);
            return $redis->lRange($message_key, (0 - $count), -1);
        }
        
        public function set_bot_user_id($uid) {
            $this->bot_user_id = $uid;
        }
        
        public function set_bot_user_name($name) {
            $this->bot_user_name = $name;
        }
        
        public function get_bot_user_name() {
            return $this->bot_user_name;
        }
        
        public function set_bot_alliance_name($name) {
            $this->ally_name = $name;
        }
        
        public function get_bot_alliance_name() {
            return $this->ally_name;
        }
        
        public function set_bot_alliance_short($short) {
            $this->ally_shortname = $short;
        }
        
        public function get_bot_alliance_short() {
            return $this->ally_shortname;
        }
        
        public function set_bot_alliance_id($aid) {
            $this->ally_id = $aid;
        }
        
        public function get_bot_alliance_id() {
            //return $this->ally_id;
            //return BOT_ALLY_ID;
            return $this->get_alliance_id($this->ally_name);
        }
        
        public function is_himself($name) {
            return (mb_strtoupper($name) == mb_strtoupper($this->bot_user_name)) ? true : false;
        }
        
        public function is_global_user(/** @noinspection PhpUnusedParameterInspection */
        $user) {
            global $redis;
            
            return ($redis->status() && $redis->hGet('aliase', mb_strtoupper($user)) !== false);
        }
        
        public function is_ally_user(/** @noinspection PhpUnusedParameterInspection */
        $user, $check_sister = false) {
            global $redis;
            
            if (!defined('BOT_ALLY_ID')||!$redis->status()) return false;
            $alliance_key = "alliance:{$this->ally_id}";
            if ($redis->sIsMember("{$alliance_key}:members", $user)) return true;
            else if ($check_sister && $this->is_sister_user($user)) return true;
            else {
                $uid = $redis->hGet('aliase', mb_strtoupper($user));
                if ($redis->hGet("user:{$uid}:data", 'alliance') == $this->ally_id) return true;
                else return false;
            }
        }
        
        public function is_sister_user(/** @noinspection PhpUnusedParameterInspection */
        $user) {
            global $redis;
            
            if (!defined('BOT_SISTER_ID')||!$redis->status()) return false;
            $sister_ids = explode('|', $this->sister_id);
            foreach ($sister_ids as $sister_id) {
                $sister_key = "alliance:{$sister_id}";
                if ($redis->sIsMember("{$sister_key}:members", $user)) return true;
                else {
                    $uid = $redis->hGet('aliase', mb_strtoupper($user));
                    if ($redis->hGet("user:{$uid}:data", 'alliance') == $sister_id) return true;

                }
            }
            return false;
        }
        
        public function get_user_id($user) {
            global $redis;
            
            if (empty($user)||!$redis->status()) return false;
            if($uid = $redis->hGet('aliase', mb_strtoupper($user))) {
                return $uid;
            } else {
                $uid = $redis->incr('primary:users');
                $redis->hMSet("aliase", array(
                    mb_strtoupper($user) => $uid
                ));
                return $uid;
            }
        }
        
        public function get_alliance_id($alliance) {
            global $redis;
            
            if (empty($alliance)) return 0;
            if (!$redis->status()) return false;
            if ($uid = $redis->hGet('aliase', mb_strtoupper($alliance))) {
                return $uid;
            } else {
                $uid = $redis->incr('primary:alliances');
                $redis->hMSet("aliase", array(
                    mb_strtoupper($alliance) => $uid
                ));
                return $uid;
            }
        }
        
        public function get_user_by_hash($hash) {
            global $redis;
            
            if (empty($hash)||!$redis->status()) return false;
            return $redis->hGet('hashes', $hash);
        }
        
        public function set_user_hash($user) {
            global $redis;
            
            if (empty($user)||!$redis->status()) return false;
            if ($uid = $this->get_user_id($user)) {
                $newhash = md5(uniqid($uid, true));
                if ($oldhash = $redis->hGet("user:{$uid}:data", 'hash')) $redis->hDel('hashes', $oldhash);
                $redis->hMSet("user:{$uid}:data", array(
                    'hash' => $newhash,
                    'name' => $user
                ));
                $redis->hSet('hashes', $newhash, $uid);
                return $newhash;
            } else return false;
        }
        
        public function set_hash($user, $extension) {
            global $redis;
            
            if (empty($user)||!$redis->status()) return false;
            if ($uid = $this->get_user_id($user)) {
                $newhash = md5(uniqid($uid, true));
                if($oldhash = $redis->hGet("user:{$uid}:data", $extension)) $redis->hDel('hashes', $oldhash);
                $redis->hMSet("user:{$uid}:data", array(
                    $extension => $newhash,
                    'name' => $user
                ));
                $redis->hSet('hashes', $newhash, $uid);
                return $newhash;
            } else return false;
        }
        
        public function del_hash($user, $extension) {
            global $redis;
            
            if (empty($user)||!$redis->status()) return false;
            if ($uid = $this->get_user_id($user)) {
                if($oldhash = $redis->hGet("user:{$uid}:data", $extension)) {
                    $redis->hDel('hashes', $oldhash);
                    $redis->hDel("user:{$uid}:data", $extension);
                }
                return true;
            } else return false;
        }
        
        public function get_user_name_by_id($uid) {
            global $redis;
            
            if (empty($uid)||!$redis->status()) return false;
            return $redis->hGet("user:{$uid}:data", 'name');
        }
        
        public function get_user_aliance_by_id($uid) {
            global $redis;
            
            if (empty($uid)||!$redis->status()) return false;
            return $redis->hGet("user:{$uid}:data", 'alliance');
        }
        
        public function get_alliance_name_by_id($aid) {
            global $redis;
            
            if (empty($aid)||!$redis->status()) return false;
            return $redis->hGet("alliance:{$aid}:data", 'name');
        }
        
        public function get_alliance_rights_by_id($aid) {
            global $redis;
            
            if (empty($aid)||!$redis->status()) return false;
            return $redis->hGetAll("alliance:{$aid}:rights");
        }
        
        public function get_user_random_nick_by_id($uid) {
            global $redis;
            
            if (empty($uid)||!$redis->status()) return false;
            return $redis->sRandMember("user:{$uid}:alias");
        }
        
        public function get_random_nick($user) {
            global $redis;
            
            if (empty($user)) return false;
            else if ($redis->status()) {
                $uid = $this->get_user_id($user);
                $alias = $redis->sRandMember("user:{$uid}:alias");
            } 
            if ($alias) return $alias;
            else return $user;
        }
        
        public function get_random_member($aid) {
            global $redis;
            
            if (empty($aid)||!$redis->status()) return false;
            else if ($redis->status()) {
                $alias = $redis->sRandMember("alliance:{$aid}:members");
            } 
            if ($alias) return $alias;
            else return $user;
        }
        
        public function get_nick($user) {
            global $redis;
            
            if (empty($user)) return false;
            else if ($redis->status()) {
                $uid = $this->get_user_id($user);
                $alias = $redis->hGet("user:{$uid}:data", 'name');
            }
            if ($alias) return $alias;
            else return $user;
        }
        
        public function get_lang($user) {
            global $redis;
            
            if (empty($user)) return false;
            else if ($redis->status()) {
                $uid = $this->get_user_id($user);
                $lang = $redis->hGet("user:{$uid}:data", 'lang');
            }
            if ($lang) return $lang;
            else return BOT_LANG;
        }
        
        public function get_rank_by_id($uid) {
            global $redis;
            
            if (empty($uid)) return false;
            else if ($redis->status()) {
                $rank = $redis->hGet("user:{$uid}:data", 'alliance_rank_id');
                return $rank;
            }
            else return false;
        }
        
        public function is_op_user($user, $check_sister = false) {
            global $redis;
            
            if (empty($user)) return false;
            else if ($redis->status() && defined('BOT_ALLY_ID')) {
                $uid = $this->get_user_id($user);
                $rank = $redis->hGet("user:{$uid}:data", 'alliance_rank_id');
                if ($rank && $rank <= \ROLES::OFFICER && $this->is_ally_user($user, $check_sister)) return true;
                else return ($user == $this->owner) ? true : false;
            }
            else return ($user == $this->owner) ? true : false;
        }
        
        public function has_rank($user, $needle, $check_sister = false) {
            global $redis;
            
            if (empty($user)) return false;           
            else if ($redis->status() && defined('BOT_ALLY_ID')) {
                $uid = $this->get_user_id($user);
                $rank = $redis->hGet("user:{$uid}:data", 'alliance_rank_id');
                if ($rank && $rank <= $needle && $this->is_ally_user($user, $check_sister)) return true;
            }
            return false;
        }
        
        public function is_owner($user) {
            return ($user == $this->owner) ? true : false;
        }
        
        public function setDebug($debug) {
            $this->debug = (bool) $debug;
        }
        
        public function isDebug() {
            return $this->debug;
        }
        
        public function getLoop() {
            return $this->loop;
        }
        
        public function reload() {
            $this->stop = true;
            if ($this->load_hooks(true)) $this->stop = false;
            
            return true;
        }
        
        public static function is_string_pos($string) {
            return preg_match('/^[0-9]{1,3}:[0-9]{1,3}$/', strtolower($string));
        }
        
        public static function is_string_continent($string) {
            $conti = CotG_Bot::get_continent_abbr();
            $pattern = '/^'.$conti.'[0-9]{1,2}$/i';
            return preg_match($pattern, strtolower($string));
        }
        
        public static function get_continent_by_string($string) {
            $conti = CotG_Bot::get_continent_abbr();
            $pattern = '/^'.$conti.'([0-9]{1,2})$/i';
            preg_match($pattern, strtolower($string), $match);
            return (isset($match[1]) ? intval($match[1]) : null);
        }
        
        public static function get_pos_by_string($string) {
            list($x, $y) = preg_split('/:/', strtolower($string), 2);
            return str_pad($x, 3 ,'0', STR_PAD_LEFT).':'.str_pad($y, 3 ,'0', STR_PAD_LEFT);
        }
        
        public static function get_continent_by_koords($x, $y) {
            return (floor( $y/100 ) * 10 + floor( $x/100 ) );
        }
        
        public static function get_continent_by_pos($pos) {
            list($x, $y) = explode(':', $pos, 2);
            return self::get_continent_by_koords(abs($x), abs($y));
        }
        
        public static function get_continent_abbr() {
            return 'C';
        }
        
        public function get_server_time() {
            $_local_time = number_format((microtime(true) + date('Z')) * 1000, 0, '.', '');
            $_with_offset = $_local_time + ((abs($this->offset) >= 1000) ? $this->offset : 0);
            if ($this->debug) $this->log("Get server time: " . print_r(array('localtime'=> $_local_time, 'offset' => $this->offset, 'servertime' => $_with_offset), true), Log\Logger::DEBUG);
            return $_with_offset;
        }
        
        public function get_timestamp() {
            $time = new \DateTime();
            return $time->getTimestamp() + $time->getOffset();
        }
        
        public function get_encrypted($hash, $key) {
                                                                    
                              
                              
                                           
                                                                     

            return \AesCtr::encrypt($hash, $key, 256);
        }
    }            
