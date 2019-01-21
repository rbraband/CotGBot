<?php
    
    namespace CotG\Bot\Data;
    
    use FastSimpleHTMLDom\Document;
    use Zend\Log\LoggerAwareInterface;
    use Zend\Log\LoggerInterface;
    use CotG\Bot\Observer;
    use CotG\Bot\Json;
    use CotG\Bot\Log;
    use CotG\Bot;
    
    class Analyser extends Observer\Observable implements LoggerAwareInterface {
        
        protected $logger;
        
        public $note = array();
        
        public function __construct() {
            
        }
        
        public function setLogger(LoggerInterface $logger) {
            $this->logger = $logger;
        }
        
        static function factory($observer) {
            
            // New Data Object
            $data = new Analyser;
            
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
        
        // shorthand for timestamp include timezone offset
        private static function get_timestamp() {
            $time = new \DateTime();
            return $time->getTimestamp() + $time->getOffset();
        }
        
        public function using($data, $type, $debug = false) {
            if (!empty($data)) $json = Json\Json::Decode($data); // second parameter to true toAssoc
            
            switch ($type) {
                case 'PLAYER|STATISTICS':
                $count_data = count($json[0]);
                
                if ($count_data > 0) {
                    if ($debug) $this->debug("Using " . $count_data . " {$type} data.");
                    $this->get_player_stat_multi($json[0], $debug);
                }
                break;              
                case (preg_match('/PLAYER_(\w{1,})\|DATA/', $type, $match) ? true : false):
                $id = $match[1];
                if (!is_null($json)) {
                    if ($debug) $this->debug("Using {$type} for {$id}.");
                    $this->get_player_data($id, $json, $debug);
                }
                case (preg_match('/PLAYER_(\w{1,})\|CITIES/', $type, $match) ? true : false):
                $id = $match[1];
                if (!is_null($json)) {
                    if ($debug) $this->debug("Using {$type} for {$id}.");
                    $this->get_player_cities($id, $json, $debug);
                }
                break;
                case (preg_match('/PLAYER_(\w{1,})\|TROOPS/', $type, $match) ? true : false):
                $id = $match[1];
                if (!is_null($json)) {
                    if ($debug) $this->debug("Using {$type} for {$id}.");
                    $this->get_player_troops($id, $json, $debug);
                }
                break;
                case (preg_match('/PLAYER_(\w{1,})\|REINFORCEMENTS/', $type, $match) ? true : false):
                $id = $match[1];
                if (!is_null($json)) {
                    if ($debug) $this->debug("Using {$type} for {$id}.");
                    $this->get_player_reinforcements($id, $json, $debug);
                }
                break;
                case (preg_match('/PLAYER_(\w{1,})\|SUBSTITUTE/', $type, $match) ? true : false):
                $id = $match[1];
                if (!is_null($json)) {
                    if ($debug) $this->debug("Using {$type} for {$id}.");
                    $this->get_player_substitute($id, $json, $debug);
                }
                break;
                case (preg_match('/CONTINENT_(\w{2})\|PLAYER\|STATISTICS/', $type, $match) ? true : false):
                $continent = $match[1];
                $count_data = count($json[0]);
                
                if ($count_data > 0) {
                    if ($debug) $this->debug("Using C{$continent} {$count_data} {$type} data.");
                    $this->get_continent_player_stat_multi($continent, $json[0], $debug);
                    }
                break;
                case 'ALLIANCE|STATISTICS':
                $count_data = count((array)$json->{'1'});
                
                if ($count_data > 0) {
                    if ($debug) $this->debug("Using {$count_data} {$type} data.");
                    $this->get_alliance_stat_multi($json->{'1'}, $debug);
                }
                break;
                case 'ALLIANCE|MILITARY':
                $count_data = count((array)$json->{'20'});

                if ($count_data > 0) {
                    if ($debug) $this->debug("Using {$count_data} {$type} data.");
                    $this->get_alliance_mil_multi($json->{'20'}, $debug);
                }
                break;
                case 'ALLIANCE|SHRINES':
                $count_data = count((array)$json->{'14'});

                if ($count_data > 0) {
                    if ($debug) $this->debug("Using {$count_data} {$type} data.");
                    $this->get_alliance_shrine_multi($json->{'14'}, $debug);
                }
                break;
                case 'ALLIANCE|CITIES':
                if (!is_null($json)) {
                    if ($debug) $this->debug("Using {$type} data.");
                    $this->get_alliance_cities($json, $debug);
                }
                break;
                case 'ALLIANCE|MEMBERS':
                if (!is_null($json)) {
                    if ($debug) $this->debug("Using {$type} data.");
                    $this->get_alliance_members($json, $debug);
                }
                break;
                case (preg_match('/ALLIANCE_(\w{1,})\|DATA/', $type, $match) ? true : false):
                $id = $match[1];
                
                if (!is_null($json)) {
                    if ($debug) $this->debug("Using {$type} for {$id}.");
                    $this->get_alliance_data($id, $json, $debug);
                }
                case 'ALLIANCE|INANDOUT':
                if (!is_null($json)) {
                    if ($debug) $this->debug("Using {$type} data.");
                    $this->get_alliance_io($json, $debug);
                }
                break;
                case (preg_match('/CONTINENT_(\w{2})\|ALLIANCE\|STATISTICS/', $type, $match) ? true : false):
                $continent = $match[1];
                $count_data = count((array)$json->{'1'});
                
                if ($count_data > 0) {
                    if ($debug) $this->debug("Using C{$continent} {$count_data} {$type} data.");
                    $this->get_continent_alliance_stat_multi($continent, $json->{'1'}, $debug);
                }
                break;
                case (preg_match('/CONTINENT_(\w{2})\|ALLIANCE\|MILITARY/', $type, $match) ? true : false):
                $continent = $match[1];
                $count_data = count((array)$json->{'20'});
                
                if ($count_data > 0) {
                    if ($debug) $this->debug("Using C{$continent} {$count_data} {$type} data.");
                    $this->get_continent_alliance_mil_multi($continent, $json->{'20'}, $debug);
                }
                break;
                case (preg_match('/CONTINENT_(\w{2})\|ALLIANCE\|SHRINES/', $type, $match) ? true : false):
                $continent = $match[1];
                $count_data = count((array)$json->{'14'});
                
                if ($count_data > 0) {
                    if ($debug) $this->debug("Using C{$continent} {$count_data} {$type} data.");
                    $this->get_continent_alliance_shrine_multi($continent, $json->{'14'}, $debug);
                }
                break;
                case 'SYSTEM|POLL':
                if (!is_null($json)) {
                    if ($debug) $this->debug("Using {$type} data.");
                    $this->get_system_poll($json, $debug);
                }
                break;
                case 'CONTINENT|STATISTICS':
                $count_data = count((array)$json->{'b'});

                if ($count_data > 0) {
                    if ($debug) $this->debug("Using " . $count_data . " {$type} data.");
                    $this->get_continent_stat_multi($json->{'b'}, $debug);
                }
                break;
                default:
                    $this->error("No {$type} data resposible.");
                
            }
            
            if (is_null($json)) {
                $this->error("Mishmashed {$type} data.");
                if ($debug) var_dump($data);
            }
            _destroy($json, $data, $type, $count_data, $id, $match, $continent);
        }
        
        private function get_continent_stat_multi($continents, $debug = false) {
            if (is_object($continents)) {
                foreach($continents as $key => $continent) {
                    if ($key == 56) $key = 'all'; // ugly hack!!!
                    if (empty($continent[0])) {
                        $this->error("Using CONTINENT data: " . Json\Json::Encode($continent));
                        continue;
                    }
                    $this->note = self::stat_continent($key, $continent);
                    if ($debug) $this->debug("Publish stat for C{$key}!");
                    $this->notify();
                }
            }
            _destroy($continents, $continent);
        }
        
        private function get_player_stat_multi($players, $debug = false) {
            if (is_array($players)) {
                foreach($players as $key => $player) {
                    if (empty($player->{'1'})) {
                        $this->error("Using PLAYER data: " . Json\Json::Encode($player));
                        continue;
                    }
                    $this->note = self::stat_player($player);
                    if ($debug) $this->debug("Publish stat for {$player->{'1'}}!");
                    $this->notify();
                }
            }
            _destroy($players, $player);
        }
        
        private function get_continent_player_stat_multi($continent, $players, $debug = false) {
            if (is_array($players)) {
                foreach($players as $key => $player) {
                    if (empty($player->{'1'})) {
                        $this->error("Using PLAYER data for C{$continent}: " . Json\Json::Encode($player));
                        continue;
                    }
                    $this->note = self::stat_continent_player($continent, $player);
                    if ($debug) $this->debug("Publish C{$continent} stat for {$player->{'1'}}!");
                    $this->notify();
                }
            }
            _destroy($continent, $players, $player);
        }
        
        private static function stat_continent($continent, $data) {
            
            $note = array(
            'type'          => STATISTICS,
            'id'            => CONTINENT,
            'continent'     => $continent,
            'data'          => self::analyse_continent($continent, $data));
            _destroy($data);
            return $note;
        }
        
        private static function analyse_continent($id, $data) {
            
            $note = array(
            'type'          => CONTINENT,
            'id'            => (string)   $id,
            'free_count'    => (int) $data[0],
            'settled_count' => (int) $data[1],
            'cities_count'  => (int) $data[2],
            'castle_count'  => (int) $data[3],
            'temple_count'  => (int) $data[4],
            'cavern_count'  => (int) $data[5],
            'boss_count'    => (int) $data[6]);
            _destroy($data);
            return $note;
        }
        
        private static function stat_player($data) {
            
            $note = array(
            'type'          => STATISTICS,
            'id'            => PLAYER,
            'data'          => self::analyse_player($data));
            _destroy($data);
            return $note;
        }
        
        private static function stat_continent_player($continent, $data) {
            
            $note = array(
            'type'          => STATISTICS,
            'id'            => CONTINENT . '|' . PLAYER,
            'continent'     => $continent,
            'data'          => self::analyse_player($data));
            _destroy($data);
            return $note;
        }
        
        private static function analyse_player($data) {
            
            $note = array(
            'type'          => PLAYER,
            'name'          => (string) $data->{'1'},
            'alliance'      => (string) $data->{'4'},
            'cities'        => (int)    $data->{'5'},
            'points'        => (int)    $data->{'3'},
            'rank'          => (int)    $data->{'2'});
            _destroy($data);
            return $note;
        }
        
        private function get_system_poll($data, $debug = false) {
            if (!empty($data->{'cssrs'})) {
                /* 
                kick   = 0
                online = 1
                */
            }
            if (!empty($data->{'notify'})) {
                $this->note = self::poll_notify($data->{'notify'});
                if ($debug) $this->debug("Publish poll-data notify!");
                $this->notify();
            }
            if (!empty($data->{'server'})) {
                /* server_id */
            }
            if (!empty($data->{'player'})) {
                $this->note = self::poll_self($data->{'player'});
                if ($debug) $this->debug("Publish poll-data bot!");
                $this->notify();
            }
            if (!empty($data->{'alliance'}) && $data->{'alliance'} !== 1) {
                $this->note = self::poll_alliance($data->{'alliance'});
                if ($debug) $this->debug("Publish poll-data alliance!");
                $this->notify();
            }
            if ((int) $data->{'mail'} >= 1) {
                /* mail_count */
                if ($debug) $this->debug("You got {$data->{'mail'}} mail!");
            }
            if ((int) $data->{'aic'} >= 1) {
                /* invoice_count */
                $html = new Document();
                $html->loadHtml($data->{'aiv'});
                if ($debug) $this->debug("You receive {$data->{'aic'}} invitation!");
                foreach($html->find('div[id=invdivta]') as $inv) {
                    $table = $inv->find('table[id=allyinvtable]');
                    $from = $table->find('td[id=inviteFromAlliance]')->plaintext;
                    $by = $table->find('td[id=sentbyplayerally]')->plaintext;
                    $to = $table->find('td[id=invvaliduntil]')->plaintext;
                    $id = $inv->find('button[id=acceptAllianceInviteGo]', 0)->getAttribute('a');
                    $this->note = self::poll_invoice(array(
                        'from' => $from,
                        'by'   => $by,
                        'to'   => $to,
                        'id'   => $id
                    ));
                    if ($debug) $this->debug("Publish invoice-data {$by}@{$from}!");
                    $this->notify();
                }
            }
            _destroy($data);
        }
        
        private function get_alliance_io($data, $debug = false) {
            if (isset($data->{'inc'})) {
                $this->note = self::inc_alliance($data->{'inc'});
                if ($debug) $this->debug("Publish data for incomings!");
                $this->notify();
            }
            if (isset($data->{'out'})) {
                $this->note = self::out_alliance($data->{'out'});
                if ($debug) $this->debug("Publish data for outgoings!");
                $this->notify();
            }
            if (!empty($data->{'el'})) {
                
            }
            _destroy($data);
        }
        
        private function get_player_troops($id, $troops_array, $debug = false) {
            if (is_array($troops_array)) {
                foreach($troops_array as $key => $city) {
                    if (empty($city->{'id'})) {
                        $this->error("Using TROOP data: " . Json\Json::Encode($city));
                        continue;
                    }
                    $this->note = self::city_troops($id, $city);
                    if ($debug) $this->debug("Publish troops for {$this->note['data']['coords']}!");
                    $this->notify();
                }
            }
            _destroy($id, $troops_array, $key, $city);
        }
        
        private function get_player_reinforcements($id, $reinforcements_array, $debug = false) {
            if (is_array($reinforcements_array)) {
                foreach($reinforcements_array as $key => $reinforcements) {
                    $this->note = self::city_reinforcements($id, $reinforcements);
                    if ($debug) $this->debug("Publish reinforcements for {$this->note['data']['coords']}!");
                    $this->notify();
                }
            }
            _destroy($id, $reinforcements_array, $key, $reinforcements);
        }
        
        private function get_player_cities($id, $cities_array, $debug = false) {
            if (is_array($cities_array)) {
                foreach($cities_array as $key => $city) {
                    if (empty($city->{'id'})) {
                        $this->error("Using CITY info: " . Json\Json::Encode($city));
                        continue;
                    }
                    $this->note = self::city_info($id, $city);
                    if ($debug) $this->debug("Publish info for {$this->note['data']['coords']}!");
                    $this->notify();
                }
            }
            _destroy($id, $cities_array, $key, $city);
        }
        
        private function get_player_data($id, $data, $debug = false) {
            if (!is_null($data)) {
                if (empty($data->{'player'})) {
                    $this->error("Using PALYER {$id} data: " . Json\Json::Encode($data));
                    $this->note = self::data_player($id, $data);
                    if ($debug) $this->debug("Publish data for deleted {$id}!");
                } else {
                    $this->note = self::data_player($id, $data);
                    if ($debug) $this->debug("Publish data for {$data->{'player'}}!");
                }
                $this->notify();
            }
            _destroy($id, $data);
        }
        
        private function get_alliance_cities($cities_array, $debug = false) {
            if (is_array($cities_array)) {
                foreach($cities_array as $key => $city) {
                    if (empty($city->{'id'})) {
                        $this->error("Using CITY data: " . Json\Json::Encode($city));
                        continue;
                    }
                    $this->note = self::city_data($city);
                    if ($debug) $this->debug("Publish data for {$this->note['data']['coords']}!");
                    $this->notify();
                }
            }
            _destroy($cities_array, $key, $city);
        }
        
        private function get_alliance_members($members_array, $debug = false) {
            if (is_array($members_array)) {
                $_members_array = [];
                foreach($members_array as $key => $member) {
                    if (empty($member->{'name'})) {
                        $this->error("Using MEMBER data: " . Json\Json::Encode($member));
                        continue;
                    }
                    array_push($_members_array, $member->{'name'});
                    $this->note = self::member_data($member);
                    if ($debug) $this->debug("Publish data for {$this->note['data']['name']}!");
                    $this->notify();
                }
                if (!empty($_members_array)) {
                    $this->note = self::members_data($_members_array);
                    $_count_members_array = count($_members_array);
                    if ($debug) $this->debug("Publish {$_count_members_array} MEMBERS!");
                    $this->notify();
                }
            }
            _destroy($members_array, $key, $member);
        }
        
        private function get_player_substitute($id, $substitute, $debug = false) {
            if (!is_null($substitute)) {
                $this->note = self::user_substitute($id, $substitute);
                if ($debug) $this->debug("Publish substitute for user {$id}}!");
                $this->notify();
            }
            _destroy($id, $substitute);
        }
        
        private function get_alliance_data($id, $alliance, $debug = false) {
            if (!is_null($alliance)) {
                if (empty($alliance->{'id'})) {
                    $this->error("Using ALLIANCE {$id} data: " . Json\Json::Encode($alliance));
                    $this->note = self::data_alliance($id, $alliance);
                    if ($debug) $this->debug("Publish data for deleted {$id}!");
                } else {
                    $this->note = self::data_alliance($id, $alliance);
                    if ($debug) $this->debug("Publish data for {$alliance->{'n'}}!");
                }
                $this->notify();
            }
            _destroy($id, $alliance);
        }
        
        private function get_alliance_stat_multi($alliances, $debug = false) {
            if (is_array($alliances)) {
                foreach($alliances as $key => $alliance) {
                    if (empty($alliance->{'1'})) {
                        $this->error("Using ALLIANCE data: " . Json\Json::Encode($alliance));
                        continue;
                    }
                    $this->note = self::stat_alliance($alliance);
                    if ($debug) $this->debug("Publish stat for {$alliance->{'1'}}!");
                    $this->notify();
                }
            }
            _destroy($alliances, $key, $alliance);
        }
        
        private function get_alliance_mil_multi($alliances, $debug = false) {
            if (is_array($alliances)) {
                foreach($alliances as $key => $alliance) {
                    if (empty($alliance->{'2'})) {
                        $this->error("Using ALLIANCE military: " . Json\Json::Encode($alliance));
                        continue;
                    }
                    $this->note = self::mil_alliance($alliance);
                    if ($debug) $this->debug("Publish mil for {$alliance->{'2'}}!");
                    $this->notify();
                }
            }
            _destroy($alliances, $key, $alliance);
        }
        
        private function get_alliance_shrine_multi($alliances, $debug = false) {
            if (is_array($alliances)) {
                foreach($alliances as $key => $alliance) {
                    if (empty($alliance->{'4'})) {
                        $this->error("Using ALLIANCE shrines: " . Json\Json::Encode($alliance));
                        continue;
                    }
                    $this->note = self::shrine_alliance($alliance);
                    if ($debug) $this->debug("Publish shrines for {$alliance->{'4'}}!");
                    $this->notify();
                }
            }
            _destroy($alliances, $key, $alliance);
        }
        
        private function get_continent_alliance_stat_multi($continent, $alliances, $debug = false) {
            if (is_array($alliances)) {
                foreach($alliances as $key => $alliance) {
                    if (empty($alliance->{'1'})) {
                        $this->error("Using ALLIANCE data for C{$continent}: " . Json\Json::Encode($alliance));
                        continue;
                    }
                    $this->note = self::stat_continent_alliance($continent, $alliance);
                    if ($debug) $this->debug("Publish C{$continent} stat for {$alliance->{'1'}}!");
                    $this->notify();
                }
            }
            _destroy($continent, $alliances, $key, $alliance);
        }
        
        private function get_continent_alliance_mil_multi($continent, $alliances, $debug = false) {
            if (is_array($alliances)) {
                foreach($alliances as $key => $alliance) {
                    if (empty($alliance->{'2'})) {
                        $this->error("Using ALLIANCE mil for C{$continent}: " . Json\Json::Encode($alliance));
                        continue;
                    }
                    $this->note = self::mil_continent_alliance($continent, $alliance);
                    if ($debug) $this->debug("Publish C{$continent} mil for {$alliance->{'2'}}!");
                    $this->notify();
                }
            }
            _destroy($continent, $alliances, $key, $alliance);
        }
        
        private function get_continent_alliance_shrine_multi($continent, $alliances, $debug = false) {
            if (is_array($alliances)) {
                foreach($alliances as $key => $alliance) {
                    if (empty($alliance->{'4'})) {
                        $this->error("Using ALLIANCE shrines for C{$continent}: " . Json\Json::Encode($alliance));
                        continue;
                    }
                    $this->note = self::mil_continent_alliance($continent, $alliance);
                    if ($debug) $this->debug("Publish C{$continent} shrines for {$alliance->{'4'}}!");
                    $this->notify();
                }
            }
            _destroy($continent, $alliances, $key, $alliance);
        }
        
        private static function inc_alliance($data) {
            
            $note = array(
            'type'          => ALLIANCE,
            'id'            => INCOMINGS,
            'data'          => self::analyse_incomings($data),
            'origin'        => $data);
            _destroy($data);
            return $note;
        }
        
        private static function out_alliance($data) {
            
            $note = array(
            'type'          => ALLIANCE,
            'id'            => OUTGOINGS,
            'data'          => self::analyse_outgoings($data),
            'origin'        => $data);
            _destroy($data);
            return $note;
        }
        
        private static function poll_notify($data) {
            
            $note = array(
            'type'          => POLL,
            'id'            => NOTIFY,
            'data'          => self::analyse_poll_notify($data));
            _destroy($data);
            return $note;
        }
        
        private static function poll_self($data) {
            
            $note = array(
            'type'          => POLL,
            'id'            => BOT,
            'data'          => self::analyse_poll_self($data));
            _destroy($data);
            return $note;
        }
        
        private static function poll_invoice($data) {
            
            $note = array(
            'type'          => POLL,
            'id'            => INVOICE,
            'data'          => self::analyse_poll_invoice($data));
            _destroy($data);
            return $note;
        }
        
        private static function poll_alliance($data) {
            
            $note = array(
            'type'          => POLL,
            'id'            => ALLIANCE,
            'data'          => self::analyse_poll_alliance($data));
            _destroy($data);
            return $note;
        }
        
        private static function stat_alliance($data) {
            
            $note = array(
            'type'          => STATISTICS,
            'id'            => ALLIANCE,
            'data'          => self::analyse_alliance($data));
            _destroy($data);
            return $note;
        }
        
        private static function mil_alliance($data) {
            
            $note = array(
            'type'          => MILITARY,
            'id'            => ALLIANCE,
            'data'          => self::military_alliance($data));
            _destroy($data);
            return $note;
        }
        
        private static function shrine_alliance($data) {
            
            $note = array(
            'type'          => SHRINES,
            'id'            => ALLIANCE,
            'data'          => self::shrines_alliance($data));
            _destroy($data);
            return $note;
        }
        
        private static function city_troops($id, $data) {
            
            $note = array(
            'type'          => DATA,
            'id'            => USER . '|' . TROOPS,
            'user'          => $id,
            'data'          => self::analyse_troops($data));
            _destroy($data);
            return $note;
        }
        
        private static function city_reinforcements($id, $data) {
            
            $note = array(
            'type'          => DATA,
            'id'            => USER . '|' . REINFORCEMENTS,
            'user'          => $id,
            'data'          => self::analyse_reinforcements($data));
            _destroy($data);
            return $note;
        }
        
        private static function city_info($id, $data) {
            
            $note = array(
            'type'          => DATA,
            'id'            => USER . '|' . CITY,
            'user'          => $id,
            'data'          => self::analyse_city_info($data));
            _destroy($data);
            return $note;
        }
        
        private static function city_data($data) {
            
            $note = array(
            'type'          => ALLIANCE,
            'id'            => CITY,
            'data'          => self::analyse_city_data($data));
            _destroy($data);
            return $note;
        }
        
        private static function member_data($data) {
            
            $note = array(
            'type'          => ALLIANCE,
            'id'            => MEMBER,
            'data'          => self::analyse_member_data($data));
            _destroy($data);
            return $note;
        }
        
        private static function members_data($data) {
            
            $note = array(
            'type'          => ALLIANCE,
            'id'            => MEMBERS,
            'data'          => self::analyse_members_data($data));
            _destroy($data);
            return $note;
        }
        
        private static function user_substitute($id, $data) {
            
            $note = array(
            'type'          => DATA,
            'id'            => USER . '|' . SUBSTITUTE,
            'user'          => $id,
            'data'          => self::analyse_substitute($data));
            _destroy($data);
            return $note;
        }
        
        private static function data_alliance($id, $data) {
            
            $note = array(
            'type'          => DATA,
            'id'            => ALLIANCE,
            'alliance'      => $id,
            'data'          => self::analyse_alliance_data($data));
            _destroy($data);
            return $note;
        }
        
        private static function data_player($id, $data) {
            
            $note = array(
            'type'          => DATA,
            'id'            => PLAYER,
            'player'        => $id,
            'data'          => self::analyse_player_data($data));
            _destroy($data);
            return $note;
        }
        
        private static function stat_continent_alliance($continent, $data) {
            
            $note = array(
            'type'          => STATISTICS,
            'id'            => CONTINENT . '|' . ALLIANCE,
            'continent'     => $continent,
            'data'          => self::analyse_alliance($data));
            _destroy($data);
            return $note;
        }
        
        private static function mil_continent_alliance($continent, $data) {
            
            $note = array(
            'type'          => MILITARY,
            'id'            => CONTINENT . '|' . ALLIANCE,
            'continent'     => $continent,
            'data'          => self::military_alliance($data));
            _destroy($data);
            return $note;
        }
        
        private static function shrines_continent_alliance($continent, $data) {
            
            $note = array(
            'type'          => SHRINES,
            'id'            => CONTINENT . '|' . ALLIANCE,
            'continent'     => $continent,
            'data'          => self::shrines_alliance($data));
            _destroy($data);
            return $note;
        }
        
        private static function analyse_incomings($data) {
            
            $note = array();
            if (is_array($data)) foreach($data as $inc) {
                $note[] = array(
                'type'          => INCOMINGS,
                'origin'        => $inc,
                'continent'     => (string) $inc->{'tco'},
                'moongate'      => (int)    $inc->{'mg'},
                'sieging'       => (($inc->{'ty'} == 'Sieging') ? 1 : 0),
                'internal'      => (($inc->{'ty'} == 'Internal Attack') ? 1 : 0),
                'confirmed'     => ((($inc->{'st'} + (60*5)) <= self::get_timestamp()) ? 1 : 0),
                'defender'      => (string) $inc->{'tpn'},
                'attacker'      => (string) $inc->{'apn'},
                'alliance'      => (string) $inc->{'aan'},
                'spotted'       => (string) $inc->{'spt'},
                'spotted_time'  => (string) $inc->{'st'},
                'arrived'       => (string) $inc->{'art'},
                'arrived_time'  => (string) $inc->{'tt'},
                'offence'       => (string) $inc->{'ats'},
                'defence'       => (string) $inc->{'dts'},
                'claim'         => (string) $inc->{'b'},
                'source'        => array(
                    'name'      => self::get_city_name_from_html($inc->{'acn'}),
                    'coords'    => self::get_coords_from_html($inc->{'axy'}),
                    'continent' => self::get_continent_from_html($inc->{'axy'}) 
                ),
                'target'        => array(
                    'name'      => self::get_city_name_from_html($inc->{'tcn'}),
                    'coords'    => self::get_coords_from_html($inc->{'txy'}),
                    'continent' => self::get_continent_from_html($inc->{'txy'}) 
                ),
                'serial'        => md5(Json\Json::Encode(array($inc->{'ty'},$inc->{'st'},$inc->{'tt'},$inc->{'axy'},$inc->{'txy'}))));
            }
            _destroy($data, $inc);
            return $note;
        }
        
        private static function analyse_outgoings($data) {
            
            $note = array();
            if (is_array($data)) foreach($data as $inc) {
                $note[] = array(
                'type'          => OUTGOINGS,
                'origin'        => $inc,
                'arrived'       => (string) $inc->{'art'},
                'arrived_time'  => (string) $inc->{'tt'},
                'attacker'      => (string) $inc->{'apn'},
                'alliance'      => (string) $inc->{'tan'});
            }
            /*if (is_array($data)) foreach($data as $out) {
                $note[] = array(
                'type'          => INCOMINGS,
                'continent'     => (string) $out->{'tco'},
                'moongate'      => (int)    $out->{'mg'},
                'sieging'       => (($out->{'ty'} == 'Sieging') ? 1 : 0),
                'internal'      => (($out->{'ty'} == 'Internal Attack') ? 1 : 0),
                'confirmed'     => ((($out->{'st'} + (60*5)) <= self::get_timestamp()) ? 1 : 0),
                'defender'      => (string) $out->{'tpn'},
                'attacker'      => (string) $out->{'apn'},
                'alliance'      => (string) $out->{'aan'},
                'spotted'       => (string) $out->{'spt'},
                'spotted_time'  => (string) $out->{'st'},
                'arrived'       => (string) $out->{'art'},
                'arrived_time'  => (string) $out->{'tt'},
                'offence'       => (string) $out->{'ats'},
                'defence'       => (string) $out->{'dts'},
                'claim'         => (string) $out->{'b'},
                'source'        => array(
                    'name'      => self::get_city_name_from_html($out->{'acn'}),
                    'coords'    => self::get_coords_from_html($out->{'axy'}),
                    'continent' => self::get_continent_from_html($out->{'axy'}) 
                ),
                'target'        => array(
                    'name'      => self::get_city_name_from_html($out->{'tcn'}),
                    'coords'    => self::get_coords_from_html($out->{'txy'}),
                    'continent' => self::get_continent_from_html($out->{'txy'}) 
                ),
                'serial'        => md5(Json\Json::Encode(array($out->{'ty'},$out->{'st'},$out->{'tt'},$out->{'axy'},$out->{'txy'}))));
            }*/
            _destroy($data, $out);
            return $note;
        }
        
        private static function analyse_poll_self($data) {
            /*
            paid    = alliance_id
            pid     = id
            planame = alliance_name
            pn      = name
            lcit    = last_city_id
            */
            $note = array(
            'alliance_id'   => (int) $data->{'paid'},
            'id'            => (int) $data->{'pid'},
            'alliance_name' => (string) $data->{'planame'},
            'name'          => (string) $data->{'pn'},
            'last_city_id'  => (int) $data->{'lcit'}
            );
            _destroy($data);
            return $note;
        }
        
        private static function analyse_poll_invoice($data) {
            
            $note = array(
            'from'   => trim((string) $data['from']),
            'by'     => trim((string) $data['by']),
            'to'     => (string) $data['to'],
            'id'     => (int) $data['id']
            );

            _destroy($data);
            return $note;
        }
        
        private static function analyse_poll_alliance($data) {
            /*
            ab = abbreviation
            d  = diplomacy_array
                1 = friend_array
                    id = alliance_id
                    n  = name
                2 = nap_array
                3 = foe_array
            id = id
            m  = members_array
                j   = join
                lti = last_online
                lty = 0 ?
                n   = name
                pid = player_id
                r   = role
            mc = members_count
            n  = name
            r  = right_ids
                *IO rights*
                LEAD     1
                SLEAD   15
                OFFI    28
                VETERAN 41
                MEMBER  55
                NEWBY   69
            */
            $note = array(
            'id'            => (int) $data->{'id'},
            'name'          => (string) $data->{'n'},
            'abbreviation'  => (int) $data->{'ab'},
            'members'       => self::analyse_poll_alliance_members((array)$data->{'m'}),
            'rights'        => self::analyse_poll_alliance_rights($data->{'r'}),
            );
            _destroy($data);
            return $note;
        }
        private static function analyse_poll_alliance_members($members) {
            
            $return = array();
            /*
            j   = join
            lti = last_online
            lty = 0 ?
            n   = name
            pid = player_id
            r   = role
            */
            foreach($members as $member) {
                $return[] = array(
                    'join'          => (int) $member->{'j'},
                    'last_online'   => (int) $member->{'lti'},
                    'name'          => (string) $member->{'n'},
                    'player_id'     => (int) $member->{'pid'},
                    'role'          => (int) $member->{'r'}
                );
            }
            _destroy($members, $member);
            return $return;
        }
        
        private static function analyse_poll_alliance_rights($rights) {
            
            $return = array();
            /*IO rights*/
            if      ($rights[69]) $return['io'] = \ROLES::NEWBY;
            else if ($rights[55]) $return['io'] = \ROLES::MEMBER;
            else if ($rights[41]) $return['io'] = \ROLES::VETERAN;
            else if ($rights[28]) $return['io'] = \ROLES::OFFICER;
            else if ($rights[15]) $return['io'] = \ROLES::SECOND_LEADER;
            else if ($rights[1])  $return['io'] = \ROLES::LEADER;
            /*IO 1h rights*/
            if      ($rights[70]) $return['io1h'] = \ROLES::NEWBY;
            else if ($rights[56]) $return['io1h'] = \ROLES::MEMBER;
            else if ($rights[42]) $return['io1h'] = \ROLES::VETERAN;
            else if ($rights[29]) $return['io1h'] = \ROLES::OFFICER;
            else if ($rights[16]) $return['io1h'] = \ROLES::SECOND_LEADER;
            else if ($rights[2])  $return['io1h'] = \ROLES::LEADER;
            /*shared reports rights*/
            if      ($rights[72]) $return['srep'] = \ROLES::NEWBY;
            else if ($rights[58]) $return['srep'] = \ROLES::MEMBER;
            else if ($rights[44]) $return['srep'] = \ROLES::VETERAN;
            else if ($rights[31]) $return['srep'] = \ROLES::OFFICER;
            else if ($rights[18]) $return['srep'] = \ROLES::SECOND_LEADER;
            else if ($rights[4])  $return['srep'] = \ROLES::LEADER;
            
            _destroy($rights);
            return $return;
        }
        
        private static function analyse_poll_notify($data) {
            
            $note = array();
            /*
            1  = new title (%1,%2)
            2  = conquered a city (%1,%2,%3)
            3  = conquered an abandoned city (%1,%2)
            7  = annexed an abandoned city (%1,%2)
            8  = city has been conquered (%1,%2,%3)
            9  = founding a new city (%1,%2)
            ---
            12 = alliance has conquered a city (%1,%2,%3)
            13 = alliance lost a city (%1,%2,%3)
            ---
            20 = alliance reached
            ---
            33 = alliance disbanded
            34 = new alliance announcement (%1)
            35 = alliance rank has changed (%1,%2)
            36 = alliance member earned a new title (%1,%2)
            37 = new alliance member (%1)
            ---
            45 = a mighty alliance is no more (%1)
            47 = someone has left alliance (%1)
            ---
            57 = alliance has just levelled up a Temple (%1,%2,%3,%4)
            ---
            74 = server maintenance (%1)
            */
            if (is_array($data)) foreach($data as $notify) {
                list($key, $value) = explode(',', $notify, 2);
                $note[] = array(
                'key'   => $key,
                'value' => $value
                );
            }
            _destroy($data, $notify, $key, $value);
            return $note;
        }
        
        private static function analyse_troops($data) {
            
            $note = (array) $data;
            $note['name']       = (string) $data->{'c'};
            $note['date']       = (string) self::get_timestamp();
            $note['coords']     = self::get_coords_from_string($data->{'l'});
            $note['continent']  = self::get_continent_from_string($data->{'l'});
            _destroy($data);
            return $note;
        }
        
        private static function analyse_reinforcements($data) {
            
            $note = array();
            $note['target'] = array(
                'player'        => (string) $data[0],
                'alliance'      => (string) $data[1],
                'name'          => (string) $data[2],
                'coords'        => "{$data[3]}:{$data[4]}",
                'continent'     => (string) $data[5],
                'reinforcing'   => (string) $data[6],
                'sending'       => (string) $data[7],
                'returning'     => (string) $data[8]
            );
            $note['date'] = (string) self::get_timestamp();
            $note['source'] = self::analyse_reinforcement($data[9]);
            _destroy($data);
            return $note;
        }
        
        private static function analyse_reinforcement($data) {
            
            $return = array();
            foreach($data as $reinforcement) {
                $return[] = array(
                    'state'     => (string) $data[0],
                    'name'      => (string) $data[11],
                    'coords'    => "{$data[12]}:{$data[13]}",
                    'continent' => (string) $data[7],
                    'troops'    => (string) $data[8],
                    'arrival'   => (string) $data[9]
                );
            }
            _destroy($data, $reinforcement);
            return $return;
        }
        
        private static function analyse_city_info($data) {
            
            $note = (array) $data;
            $note['name']       = (string) $data->{'city'};
            $note['date']       = (string) self::get_timestamp();
            $note['coords']     = self::get_coords_from_string($data->{'location'});
            $note['continent']  = self::get_continent_from_string($data->{'location'});
            _destroy($data);
            return $note;
        }
        
        private static function analyse_city_data($data) {
            
            $note = (array) $data;
            $note['name']       = (string) $data->{'city_name'};
            $note['date']       = (string) self::get_timestamp();
            $note['coords']     = "{$data->{'x'}}:{$data->{'y'}}";
            $note['continent']  = self::get_continent_by_koords($data->{'x'}, $data->{'y'});
            _destroy($data);
            return $note;
        }
        
        private static function analyse_member_data($data) {
            
            $note = (array) $data;
            _destroy($data);
            return $note;
        }
        
        private static function analyse_members_data($data) {
            
            $note = (array) $data;
            _destroy($data);
            return $note;
        }
        
        private static function analyse_substitute($data) {
            
            $note = array();
            $note['substitute']  = (string) $data->{'rs'}->{'p'};
            $note['substitutes'] = array();
            if (is_array($data->{'as'})) foreach($data->{'as'} as $as) $note['substitutes'][] = (string) $as->{'p'};
            if (is_array($data->{'sr'})) foreach($data->{'sr'} as $sr) $note['substitutes'][] = (string) $sr->{'p'};
            $note['date'] = (string)self::get_timestamp();
            _destroy($data);
            return $note;
        }
        
        public static function get_city_name_from_html($html) {
            preg_match('/<span [^>]*>(.*)<\/span>/i', $html, $match);
            return $match[1];
        }
        
        public static function get_continent_from_html($html) {
            preg_match('/<span [^>]*>\w(.*) \(.*\)<\/span>/i', $html, $match);
            return $match[1];
        }
        
        public static function get_continent_from_string($string) {
            preg_match('/\w (.*) \(.*\)/i', $string, $match);
            return $match[1];
        }
        
        public static function get_coords_from_html($html) {
            preg_match('/<span [^>]*>.* \((.*)\)<\/span>/i', $html, $match);
            return $match[1];
        }
        
        public static function get_coords_from_string($string) {
            preg_match('/.* \((.*)\)/i', $string, $match);
            return $match[1];
        }
        
        public static function get_continent_by_koords($x, $y) {
            return (floor( $y/100 ) * 10 + floor( $x/100 ) );
        }
        
        private static function analyse_alliance($data) {
            
            $note = array(
            'type'          => ALLIANCE,
            'name'          => (string) $data->{'1'},
            'member'        => (int) $data->{'4'},
            'cities'        => (int) $data->{'5'},
            'points'        => (int) $data->{'3'},
            'rank'          => (int) $data->{'2'});
            _destroy($data);
            return $note;
        }
        
        private static function military_alliance($data) {
            
            $note = array(
            'type'          => MILITARY,
            'name'          => (string) $data->{'2'},
            'points'        => (int) $data->{'3'},
            'rank'          => (int) $data->{'1'});
            _destroy($data);
            return $note;
        }
        
        private static function shrines_alliance($data) {
            
            $note = array(
            'type'          => SHRINES,
            'name'          => (string) $data->{'4'},
            'total'         => (int) $data->{'5'},
            'rank'          => (int) $data->{'1'},
            'evara'         => (string) $data->{'6'},
            'vexemis'       => (string) $data->{'7'},
            'ibria'         => (string) $data->{'8'},
            'merius'        => (string) $data->{'9'},
            'ylanna'        => (string) $data->{'10'},
            'naera'         => (string) $data->{'11'},
            'cyndros'       => (string) $data->{'12'},
            'domdis'        => (string) $data->{'13'},
            'levels'        => (int) $data->{'14'});
            
            _destroy($data);
            return $note;
        }
        
        private static function analyse_alliance_data($data) {
            
            $note = array(
            'type'          => ALLIANCE,
            'uid'           => (int) $data->{'id'},
            'name'          => (string) $data->{'n'},
            'member'        => (int) $data->{'mc'},
            'members'       => self::list_alliance_members($data->{'me'}),
            'short'         => (string) $data->{'ab'},
            'points'        => (int) $data->{'ts'},
            'reputation'    => (int) $data->{'as'});
            _destroy($data);
            return $note;
        }
        
        private static function analyse_player_data($data) {
            
            $note = array(
            'type'          => PLAYER,
            'uid'           => (int) $data->{'aaa'},
            'alliance'      => (string) $data->{'a'},
            'name'          => (string) $data->{'player'},
            'ranking'       => (int) $data->{'c'},
            'score'         => (int) $data->{'b'},
            'castles'       => (int) $data->{'f'},
            'temples'       => (int) $data->{'g'},
            'cities'        => self::list_player_cities($data->{'h'}));
            _destroy($data);
            return $note;
        }
        
        private static function list_alliance_members($data) {
            $members = array();
            if (is_array($data)) foreach ($data as $member) {
                array_push($members, $member->{'n'});
            }
            _destroy($data,$member);
            return $members;
        }
        
        private static function list_player_cities($data) {
            $cities = array();
            if (is_array($data)) foreach ($data as $city) {
                $_city = array(
                    'name'      => (string) $city->{'h'},
                    'score'     => (int) $city->{'a'},
                    'x'         => (string) $city->{'b'},
                    'y'         => (string) $city->{'c'},
                    'coords'    => "{$city->{'b'}}:{$city->{'c'}}",
                    'continent' => (string) $city->{'d'},
                    'date'      => (string) self::get_timestamp(),
                    'water'     => (($city->{'f'} == 1) ? 'Y' : 'N' ),
                    'castle'    => (($city->{'e'} == 1) ? 'Y' : 'N' ),
                    'temple'    => (($city->{'g'} == 1) ? 'Y' : 'N' )
                );
                $cities[$_city['coords']] = $_city;
            }
            _destroy($data, $city);
            return $cities;
        }
    }
