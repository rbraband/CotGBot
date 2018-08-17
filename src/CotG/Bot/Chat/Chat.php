<?php
    
    namespace CotG\Bot\Chat;
    
    use Zend\Log\LoggerAwareInterface;
    use Zend\Log\LoggerInterface;
    use CotG\Bot\Observer;
    use CotG\Bot\Json;
    use CotG\Bot\Log;
    
    class Chat extends Observer\Observable implements LoggerAwareInterface {
        
        protected $logger;
        
        public $note = array();
        
        private $init = false;
        
        private $last;
        
        public function __construct() {
            
        }
        
        public function setLogger(LoggerInterface $logger) {
            $this->logger = $logger;
        }
        
        static function factory($observer) {
            
            // New Chat Object
            $chat = new Chat;
            
            // Attach observer
            $chat->attach($observer);
            
            // Return the object
            return $chat;
        }
        
        public function check() {
            return true;
        }
        
        public function using($data, $debug = false) {
            $json = Json\Json::Decode($data, true); // second parameter to true toAssoc 
            $chat = $json['b']; // here we get the chat
            
            if (!empty($chat)) {
                if ($debug) $this->logger->debug("Using chat: " . Json\Json::Encode($chat));
                $notes = $this->analyse($chat);
                
                if (!empty($notes)) foreach ($notes as $note) {
                    if (!empty($note['message'])) {
                        
                        $this->note = $note;
                        if ($this->note['channel'] == PRIVATEOUT) $this->logger->info("CotG " . $this->note['channel'] . " to " . $this->note['user'] . " Message:'" . $this->note['message'] . "' from Bot");
                        else $this->logger->info("CotG " . $this->note['channel'] . " Message:'" . $this->note['message'] . "' from " . $this->note['user']);
                        $this->notify();
                    }
                }
                _destroy($notes, $note);
            } else if ($debug) $this->logger->debug("Missing chat: " . $data);
            _destroy($json, $chat);
        }
        
        private static function analyse($chat) {
            $channel = $chat['a'];
            switch ($channel) {
                case 1:
                    $channel = GLOBALIN;
                    break;
                case 2:
                    $channel = PRIVATEIN;
                    break;
                case 3:
                    $channel = PRIVATEOUT;
                    break;
                case 4:
                    $channel = ALLYIN;
                    break;
                case 5:
                    $channel = OFFICERIN;
                    break;
                case 6:
                    $channel = GAMEIN;
                    break;
                default:
                    $channel = SYSTEMIN;
            }
            
            $notes = array();
            
            $message = trim(preg_replace('/\s{2,}/', ' ', $chat['d']));
            
            $user = $chat['b'];
            
            $note = array(
                'type'    => CHAT,
                'command' => null,
                'params'  => null,
                'message' => trim(self::clean_chat(html_entity_decode($message))),
                'origin'  => $message,
                'user'    => $user,
                'channel' => $channel
            );
            // Check if data is empty
            if ($note["message"] != "") {
                $tmp             = explode(' ', $note["message"]);
                $note["command"] = trim(array_shift($tmp));
                
                // Get params
                $note["params"] = $tmp;
                $notes[]        = $note;
            }
            _destroy($chat, $note);
            return $notes;
        }
        
        static function clean_chat($chat) {
            $_chat = preg_replace(
                '/<\/?(wb|spieler|player|allianz|alliance|stadt|city|report|quote|url|coords)>/',
                '',
                strip_tags($chat)
            );
            _destroy($chat);
            return trim(
                preg_replace(
                    '/\s{2,}/',
                    ' ',
                    $_chat
                )
            );
        }
    }    