<?php
    global $bot;
    
    use CotG\Bot\Cron;
    use CotG\Bot;
    
    //to disable return;
    #return;
    
    $bot->add_category('games', array('humanice' => false, 'spamsafe' => true), PUBLICY);
    $bot->add_category('fast_games', array('humanice' => false, 'spamsafe' => false), PUBLICY);
    
    // crons / ticks
    
    $bot->add_tick_event(Cron\CronDaemon::TICK0,      // Cron key
    "ForceGamesCheck",                                // command key
    "LouBot_force_games_check",                       // callback function
    function ($bot, $data) {
        global $redis;
        if (!$redis->status()) return;
        $games_key = "games:alliance:{$bot->ally_id}";
        $time_to_solve_quiz = time() - $redis->GET("{$games_key}:quizzer:start");
        if ($redis->GET("{$games_key}:running") == QUIZZER) {
            if ($time_to_solve_quiz >= QUIZZER_TIMEOUT) return quizzer_main4(null, null, true);
            else if ($time_to_solve_quiz == (QUIZZER_TIMEOUT - 10)) $bot->add_funmsg("Hurry up, quiz ends in 10 seconds!");
        }
        $time_to_solve_hangman = time() - $redis->GET("{$games_key}:hangman:start");
        if ($redis->GET("{$games_key}:running") == HANGMAN) {
            if ($time_to_solve_hangman >= HANGMAN_TIMEOUT) return hangman_main(null, null, true);
            else if ($time_to_solve_hangman == (HANGMAN_TIMEOUT - 10)) $bot->add_funmsg("Hurry up, hangman ends in 10 seconds!");
        }
    }, 'fast_games');
    
    $bot->add_cron_event(Cron\CronDaemon::HOURLY,     // Cron key
    "ForceGamesUnload",                               // command key
    "LouBot_force_games_unload",                      // callback function
    function ($bot, $data) {
        global $redis, $hwords, $questions;
        if (!$redis->status()) return;
        $games_key = "games:alliance:{$bot->ally_id}";

        if (!$redis->GET("{$games_key}:running")) {
            _destroy($hwords, $questions);
        }
    }, 'fast_games');
    
    // callbacks
    $bot->add_msg_hook(array(PRIVATEIN, GAMEIN),
    "Points",                  // command key
    "LouBot_points",           // callback function
    true,                      // is a command PRE needet?
    '/^(points|punkte)$/i',    // optional regex for key
    function ($bot, $data) {
        global $redis;
        $games_key = "games:alliance:{$bot->ally_id}";
        $commands = array('hangman', 'quiz', 'slot');
        if(!$bot->is_himself($data['user'])) {
            if (in_array(strtolower($data['params'][0]), $commands) || empty($data['params'][0])) {
                switch (strtolower($data['params'][0])) {
                    case 'hangman':
                    if (!($points = $redis->ZSCORE("{$games_key}:hangman:points", $data['user']))) $points = 0;
                    $rank = $redis->ZREVRANK("{$games_key}:hangman:points", $data['user']);
                    break;
                    case 'quiz':
                    if (!($points = $redis->ZSCORE("{$games_key}:quizzer:points", $data['user']))) $points = 0;
                    $rank = $redis->ZREVRANK("{$games_key}:quizzer:points", $data['user']);
                    break;
                    case 'slot':
                    if (!($points = $redis->ZSCORE("{$games_key}:slot:points", $data['user']))) $points = 0;
                    $rank = $redis->ZREVRANK("{$games_key}:slot:points", $data['user']);
                    break;
                    default:
                    if (!($points = $redis->ZSCORE("points:alliance:{$bot->ally_id}", $data['user']))) $points = 0;
                    $rank = $redis->ZREVRANK("points:alliance:{$bot->ally_id}", $data['user']);
                }
                $game = (!empty($data['params'][0])) ? ' ' . ucfirst($data['params'][0]) : ' Games';
                $user = $data['user'];
                } else if ($bot->is_global_user($data['params'][0])) {
                $uid = $bot->get_user_id($data['params'][0]);
                $user = $redis->HGET("user:{$uid}:data", 'name');
                if (!($points = $redis->ZSCORE("points:alliance:{$bot->ally_id}", $user))) $points = 0;
                $rank = $redis->ZREVRANK("points:alliance:{$bot->ally_id}", $user);
                $game = ' Games';
            }
            if ($rank === false) $rank = '-'; else $rank +=1;
            if ($data["channel"] == GAMEIN) {
                if ($user == $data['user']) $bot->add_funmsg("{$user}, you earn {$points}{$game} points and your rank is: {$rank}");
                else $bot->add_funmsg("{$user}, earn {$points}{$game} point and have rank: {$rank}");
            } else {
                if ($user == $data['user']) $bot->add_privmsg("You have {$points}{$game} points and rank: {$rank}", $data['user']);
                else $bot->add_privmsg("{$user} earn {$points}{$game} points and rank: {$rank}", $data['user']);
            }
            return true;
        };
    }, 'games');
    
    $bot->add_msg_hook(
    array(PRIVATEIN, GAMEIN),  // channels
    "TopTen",                  // command key
    "LouBot_topten",           // callback function
    true,                      // is a command PRE needet?
    '/^(top|topten)$/i',       // optional regex for key
    
    /* function to call */
    function ($bot, $msg, $self) {
        global $redis;
        
        // $this was not the actual bot instance, use $bot instead!!
        
        $points_key = "points:alliance:{$bot->ally_id}";
        $return = 'TopTen: ';
        $_ranking = array();
        
        if(!$bot->is_himself($msg['user'])) {
            
            if (!($ranking = $redis->ZREVRANGE($points_key, 0, 9))) $ranking = array();
            foreach($ranking as $rank => $name) {
                $points = $redis->ZSCORE($points_key, $name);
                $rank +=1;
                $_ranking[] = "{$rank}. {$name} ({$points})";
            }
            if (!empty($_ranking)) $return .= implode(', ', $_ranking) . ' - Games points';
            else $return .= 'no ranking available!';
            
            $bot->reply_msg($msg["channel"], $return, $msg['user']);
            
            return true;
        };
    }, 'games');
    
    // *** Slot machine
    
    $bot->add_funmsg_hook("Slot",// command key
    "LouBot_slot_maschine",      // callback function
    true,                        // is a command PRE needet?
    '/^slot$/i',                 // optional regex for key
    function ($bot, $data) {
        global $redis;
        if(!$bot->is_himself($data['user'])) {
            $games_key = "games:alliance:{$bot->ally_id}";
            // get game values
            $start1 = $redis->GET("{$games_key}:slot:start1");
            $start2 = $redis->GET("{$games_key}:slot:start2");
            $start3 = $redis->GET("{$games_key}:slot:start3");
            $round = $redis->INCR("{$games_key}:slot:count");
            // setup game
            $faces = array ('Ο', '♣', '♣♣', '♣♣♣', '♥', '♠', '♠♠', '♠♠♠', '♦', '7');
            $payouts = array (
            '♣|♣|♣' => 1,
            '♣♣|♣♣|♣♣' => 3,
            '♣♣♣|♣♣♣|♣♣♣' => 7,
            '♠|♠|♠' => 5,
            '♠♠|♠♠|♠♠' => 10,
            '♠♠♠|♠♠♠|♠♠♠' => 15,
            '♥|♥|♥' => 20,
            '7|7|7' => 70,
            '♦|♦|♦' => 100,
            );
            $wheel1 = array();
            foreach ($faces as $face) {
                $wheel1[] = $face;
            }
            $wheel2 = array_reverse($wheel1);
            $wheel3 = $wheel1;
            
            $stop1 = rand(count($wheel1) + $start1, 10*count($wheel1)) % count($wheel1);
            $stop2 = rand(count($wheel2) + $start2, 10*count($wheel2)) % count($wheel2);
            $stop3 = rand(count($wheel3) + $start3, 10*count($wheel3)) % count($wheel3);
            
            $redis->SET("{$games_key}:slot:start1", $stop1);
            $redis->SET("{$games_key}:slot:start2", $stop2);
            $redis->SET("{$games_key}:slot:start3", $stop3);
            
            $result1 = $wheel1[$stop1];
            $result2 = $wheel2[$stop2];
            $result3 = $wheel3[$stop3];
            
            $bot->add_funmsg("Slot maschine: {$result1}|{$result2}|{$result3}");
            if (isset($payouts["{$result1}|{$result2}|{$result3}"])) {
                $points = $payouts["{$result1}|{$result2}|{$result3}"];
                $redis->SET("{$games_key}:slot:lastwin", $round);
                $incr = $redis->ZINCRBY("{$games_key}:slot:points", $points, $data['user']);
                $total = $redis->ZINCRBY("points:alliance:{$bot->ally_id}", $points, $data['user']);
                $bot->add_funmsg("{$data['user']} earn {$points} points and has total of {$incr}/{$total} points!");
            }
        };
    }, 'games');
    
    // *** Hangman
    
    define('HANGMAN', 'HANGMAN');
    define('HANGMAN_TIMEOUT', 90);
    
    $bot->add_funmsg_hook("Hangman",                   // command key
                          "LouBot_hangman",            // callback function
                          false,                       // is a command PRE needet?
                          '/^[!]?hangman$/i',          // optional regex for key
    function ($bot, $data) {
        global $redis, $hwords;
        if(!$bot->is_himself($data['user'])) {
            $commands = array('start', 'stop', 'halt', 'pause', 'unpause');
            $games_key = "games:alliance:{$bot->ally_id}";
            if ($data['command'][0] == PRE && (in_array(strtolower($data['params'][0]), $commands) || empty($data['params'][0]))) {
                switch (strtolower($data['params'][0])) {
                    case 'unpause':
                    if($bot->is_op_user($data['user'])) {
                        $redis->DEL("{$games_key}:hangman:pause");
                        
                    } else $bot->add_tellmsg("Ne Ne Ne!", $data['user']); 
                    break;
                    case 'pause':
                    //if($bot->is_op_user($data['user'])) {
                    $redis->SET("{$games_key}:hangman:pause", $data['user'], 600);
                    if ($redis->GET("{$games_key}:running") == HANGMAN) {
                        $redis->DEL("{$games_key}:running");
                        return hangman_main($data['user'], null, true);
                    }
                    //} else $bot->add_tellmsg("Ne Ne Ne!", $data['user']); 
                    break;
                    case 'halt':
                    case 'stop':
                    if ($redis->GET("{$games_key}:running") != HANGMAN) return $bot->add_funmsg('Games error: currently no game running!');
                    $redis->DEL("{$games_key}:running");
                    return hangman_main($data['user'], null, true);
                    break;
                    case 'start':
                    default:
                    //return $bot->add_funmsg('Games error: disabled!');
                    $ttl = $redis->ttl("{$games_key}:hangman:pause");
                    if ($redis->GET("{$games_key}:running")) $bot->add_tellmsg('Games error: another game is already running!', $data['user']);
                    else if ($ttl !== -1 && $ttl !== -2) $bot->add_funmsg('Games error: '.$redis->GET("{$games_key}:hangman:pause").' set a timeout!');
                    else {
                        $letters = array('A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z');
                        $right = array_fill_keys($letters, '-');
                        $wrong = array();
                        $redis->SET("{$games_key}:running", HANGMAN);
                        $redis->SET("{$games_key}:hangman:start", (string) time());
                        $redis->SET("{$games_key}:hangman:versuche", 0);
                        $redis->DEL("{$games_key}:hangman:player");
                        $redis->SADD("{$games_key}:hangman:player" , $data['user']);
                        $redis->SET("{$games_key}:hangman:rightstr", serialize($right));
                        $redis->SET("{$games_key}:hangman:wrongstr", serialize($wrong));
                        if (empty($hwords)) {
                            $lines = file(PERM_DATA.'hangman.' . BOT_LANG, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                            foreach ($lines as $line_num => $line) {
                                if ($line[0] != '#') $hwords[$line_num] = trim(strtoupper(htmlspecialchars($line)));
                            } _destroy($lines);
                        }
                        shuffle($hwords);
                        $rand_key = array_rand($hwords, 1);
                        $word = $hwords[$rand_key];
                        $redis->SET("{$games_key}:hangman:wort", $word);
                        // start play hangman
                        $show = '';
                        $bot->add_funmsg('Games Hangman: a new game started!');
                        $bot->add_funmsg('Hangman: ' . str_pad($show, strlen($word) , '-'));
                    }
                    break;
                }
            } else if($data['command'][0] != PRE && !empty($data['params'][0])) {
                // go play hangman
                //return $bot->add_funmsg('Games error: disabled!');
                if ($redis->GET("{$games_key}:running") == HANGMAN) return hangman_main($data['user'], strtoupper($data['params'][0]));
                else $bot->add_tellmsg('Games error: currently no game running or just end!', $data['user']);
            } else $bot->add_tellmsg('Games error: wrong parameter count!', $data['user']);
        };
    }, 'fast_games');
    
    if(!function_exists('hangman_main')) {
        function hangman_main($user = '', $guess = '', $force = false) {
            global $redis, $bot;
            $magic = 2.358;
            $games_key = "games:alliance:{$bot->ally_id}";
            $guess = trim(strtoupper($guess));
            if (empty($guess) &&!$force)
                return true;
            $word = $redis->GET("{$games_key}:hangman:wort");
            $wrong_count = (strlen($word) > 6) ? strlen($word) : 6;
            $right = unserialize($redis->GET("{$games_key}:hangman:rightstr"));
            $wrong = unserialize($redis->GET("{$games_key}:hangman:wrongstr"));
            $wordletters = str_split($word);
            $show = '';
            if (!empty($guess) && stristr($word, $guess)) {
                if (in_array($guess, $right)) return $bot->add_tellmsg("Hangman: '{$guess}' already done!", $user);
                $right[$guess] = $guess;
                $redis->SADD("{$games_key}:hangman:player" , $user);
                $redis->SET("{$games_key}:hangman:rightstr", serialize($right));
                $wordletters = str_split($word);
                foreach ($wordletters as $letter) {
                    $show .= $right[$letter];
                }
                if ($guess == $word || $show == $word) {
                    $redis->DEL("{$games_key}:running");
                    $redis->SET("{$games_key}:hangman:lastwin", $user);
                    $points = round(strlen($word) * $redis->SCARD("{$games_key}:hangman:player") * $magic / $redis->GET("{$games_key}:hangman:versuche"), 0);
                    $incr = $redis->ZINCRBY("{$games_key}:hangman:points", $points, $user);
                    $total = $redis->ZINCRBY("points:alliance:{$bot->ally_id}", $points, $user);
                    $bot->add_funmsg("Games Hangman: was solved by {$user} (*)");
                    $bot->add_funmsg("{$user} earn {$points} points and has total of {$incr}/{$total} points!");
                    $bot->add_funmsg('I was looking for: ' . $word);
                    return true;
                } else $redis->INCR("{$games_key}:hangman:versuche");
            } else {
                if (in_array($guess, $wrong)) return $bot->add_tellmsg("Hangman: '{$guess}' already done!", $user);
                $wrong[$guess] = $guess;
                if (count($wrong) >= $wrong_count || $force) {
                    $redis->DEL("{$games_key}:running");
                    $bot->add_funmsg("Games Hangman: was not resolved :!!");
                    $bot->add_funmsg('I was looking for: ' . $word);
                    return true;
                } else {
                    $redis->INCR("{$games_key}:hangman:versuche");
                    $redis->SADD("{$games_key}:hangman:player" , $user);
                    $redis->SET("{$games_key}:hangman:wrongstr", serialize($wrong));
                    foreach ($wordletters as $letter) {
                        $show .= $right[$letter];
                    }
                }
            }
            $bot->add_funmsg('Number of tries ('.$redis->GET("{$games_key}:hangman:versuche").'): ' . implode(', ', $wrong));
            $bot->add_funmsg('Hangman: ' . $show);
            return true;
        }
    }
    
    // *** Quizzer
    
    define('QUIZZER', 'QUIZZER');
    define('QUIZZER_TIMEOUT', 90);
    
    $bot->add_funmsg_hook("Quiz",                   // command key
    "LouBot_quiz",                                  // callback function
    false,                                          // is a command PRE needet?
    '/^[!]?(quiz|quizzer)$/i',                      // optional regex for key
    function ($bot, $data) {
        global $redis, $questions;
        if(!$bot->is_himself($data['user'])) {
            $commands = array('start', 'stop', 'halt', 'reload', 'pause', 'unpause');
            $games_key = "games:alliance:{$bot->ally_id}";
            if ($data['command'][0] == PRE && (in_array(strtolower($data['params'][0]), $commands) || empty($data['params'][0]))) {
                switch (strtolower($data['params'][0])) {
                    case 'unpause':
                    if($bot->is_op_user($data['user'])) {
                        $redis->DEL("{$games_key}:quizzer:pause");
                        
                    } else $bot->add_tellmsg("Ne Ne Ne!", $data['user']); 
                    break;
                    case 'pause':
                    //if($bot->is_op_user($data['user'])) {
                    $redis->SET("{$games_key}:quizzer:pause", $data['user'], 600);
                    if ($redis->GET("{$games_key}:running") == QUIZZER) {
                        $redis->DEL("{$games_key}:running");
                        return quizzer_main4(null, null, true);
                    }
                    //} else $bot->add_tellmsg("Ne Ne Ne!", $data['user']); 
                    break;
                    case 'reload':
                    if($bot->is_op_user($data['user'])) _destroy($questions);
                    else $bot->add_tellmsg("Ne Ne Ne!", $data['user']); 
                    break;
                    case 'halt':
                    case 'stop':
                    if ($redis->GET("{$games_key}:running") != QUIZZER) return $bot->add_funmsg('Games error: currently no game running!');
                    $redis->DEL("{$games_key}:running");
                    return quizzer_main4(null, null, true);
                    break;
                    case 'start':
                    default:
                    //return $bot->add_funmsg('Games error: disabled!');
                    $ttl = $redis->ttl("{$games_key}:quizzer:pause");
                    if ($redis->GET("{$games_key}:running")) $bot->add_tellmsg('Games error: another game is running!', $data['user']);
                    else if ($ttl !== -1 && $ttl !== -2) $bot->add_funmsg('Games error: '.$redis->GET("{$games_key}:quizzer:pause").' set a timeout!');
                    else {
                        if (empty($questions)) {
                            #  Category?                              (should always be on top!)
                            #  Question                               (should always stand after Category)
                            #  Answer                                 (will be matched if no regexp is provided)
                            #  Regexp?                                (use UNIX-style expressions)
                            #  Author?                                (the brain behind this question)
                            #  Level? [baby|easy|normal|hard|extreme] (difficulty)
                            #  Comment?                               (comment line)
                            #  Score? [#]                             (credits for answering this question)
                            #  Tip*                                   (provide one or more hints)
                            #  TipCycle? #                            (Specify number of generated tips)
                            $tags = array('CATEGORY', 'QUESTION', 'ANSWER', 'REGEXP', 'AUTHOR', 'LEVEL', 'COMMENT', 'SCORE', 'TIP', 'TIPCYCLE');
                            $lines = file(PERM_DATA.'questions.' . BOT_LANG, FILE_IGNORE_NEW_LINES);
                            $levels = array('baby' => 3,'easy' => 5,'normal' => 15,'hard' => 20,'extreme' => 25);
                            $i = 0;
                            foreach ($lines as $line_num => $line) {
                                if ($line[0] == '#') continue;
                                if (trim($line) == '') {
                                    if (empty($data[$i]['QUESTION'])) continue;
                                    else {
                                        $questions[] = array(
                                        'CATEGORY' => (empty($data[$i]['CATEGORY'][0])) ? 'General' : $data[$i]['CATEGORY'][0],
                                        'QUESTION' => $data[$i]['QUESTION'][0],
                                        'ANSWER'   => (strpos($data[$i]['ANSWER'][0], '#') === false) ? $data[$i]['ANSWER'][0] : preg_replace('/#(.*)#/i', '$1', $data[$i]['ANSWER'][0]),
                                        'REGEXP'   => (empty($data[$i]['REGEXP'][0])) ? generate_regexp($data[$i]['ANSWER'][0]) : generate_regexp($data[$i]['REGEXP'][0]),
                                        'AUTHOR'   => (empty($data[$i]['AUTHOR'][0])) ? '' : $data[$i]['AUTHOR'][0],
                                        'LEVEL'    => (empty($data[$i]['LEVEL'][0])) ? 'normal' : $data[$i]['LEVEL'][0],
                                        'COMMENT'  => (empty($data[$i]['COMMENT'][0])) ? '' : $data[$i]['COMMENT'][0],
                                        'SCORE'    => (empty($data[$i]['SCORE'][0])) ? ((empty($data[$i]['LEVEL'][0])) ? $levels['normal'] : $levels[$data[$i]['LEVEL'][0]]) : $levels[$data[$i]['SCORE'][0]],
                                        'TIP'      => (empty($data[$i]['TIP'])) ? array('kein Tipp') : $data[$i]['TIP'],
                                        'TIPCYCLE' => (empty($data[$i]['TIPCYCLE'][0])) ? count($data[$i]['TIP']) : $data[$i]['TIPCYCLE'][0],
                                        'SOLVED'   => false
                                        );
                                        $i++;
                                        continue;
                                    }
                                }
                                list($tag, $text) = explode(':', $line, 2);
                                $data[$i][trim(strtoupper($tag))][] = trim($text);
                            } _destroy($lines);
                        } if (empty($questions)) return $bot->add_funmsg('Games error: no questions found!');
                        do {
                            shuffle($questions);
                            $rand_key = array_rand($questions, 1);
                            $question = $questions[$rand_key];
                        } while ($question['SOLVED']);
                        $wrong = array();
                        $redis->SET("{$games_key}:running", QUIZZER);
                        $redis->SET("{$games_key}:quizzer:start", (string) time());
                        $redis->SET("{$games_key}:quizzer:versuche", 0);
                        $redis->DEL("{$games_key}:quizzer:player");
                        $redis->SADD("{$games_key}:quizzer:player" , $data['user']);
                        $redis->SET("{$games_key}:quizzer:wrongstr", serialize($wrong));
                        $redis->SET("{$games_key}:quizzer:question", $rand_key);
                        // start play quizzer
                        $category = ucfirst($question['CATEGORY']);
                        $bot->add_funmsg("Games Quiz: a new game started ({$question['LEVEL']}) - {$category}");
                        $bot->add_funmsg("Question: {$question['QUESTION']}");
                    }
                    break;
                }
            } else if($data['command'][0] != PRE && !empty($data['params'][0])) {
                // go play quizzer
                //return $bot->add_funmsg('Games error: disabled!');
                if ($redis->GET("{$games_key}:running") == QUIZZER) return quizzer_main4($data['user'], strtolower(implode(' ' , $data['params'])));
                else $bot->add_tellmsg('Games error: currently no game running or just end!', $data['user']);
            } else $bot->add_tellmsg('Games error: wrong parameter count!', $data['user']);
        };
    }, 'fast_games');
    
    if(!function_exists('quizzer_main4')) {
        function quizzer_main4($user = '', $guess = '', $force = false) {
            global $redis, $bot, $questions;
            $magic = 2.358;
            $games_key = "games:alliance:{$bot->ally_id}";
            $guess = trim(strtoupper($guess));
            if (empty($guess) && !$force)
                return true;
            $question = $redis->GET("{$games_key}:quizzer:question");
            $wrong = unserialize($redis->GET("{$games_key}:quizzer:wrongstr"));
            $wrong_count = 6;
            $bot->log('Quiz:' . $questions[$question]['REGEXP'] . ' | ' . $guess);
            $regex = "/^{$questions[$question]['REGEXP']}$/i";
            $time_to_solve = time() - $redis->GET("{$games_key}:quizzer:start");
            $redis->SADD("{$games_key}:quizzer:player" , $user);
            if (preg_match($regex, $guess) && !$force) {
                $redis->DEL("{$games_key}:running");
                $redis->SET("{$games_key}:quizzer:lastwin", $user);
                $questions[$question]['SOLVED'] = true;
                $chance = round($time_to_solve / 10, 0);
                $points = $questions[$question]['SCORE'] - round($questions[$question]['SCORE']/7*$chance);
                $minimum_points = round($questions[$question]['SCORE']/7);
                $points = ($points <= 0) ? (($minimum_points <= 0) ? 1 : $minimum_points) : $points;
                $incr = $redis->ZINCRBY("{$games_key}:quizzer:points", $points, $user);
                $total = $redis->ZINCRBY("points:alliance:{$bot->ally_id}", $points, $user);
                $bot->add_funmsg("Games Quiz: solved by {$user} (*)");
                $bot->add_funmsg("{$user} earn {$points} points and has total of {$incr}/{$total} points!");
                $bot->add_funmsg('I was looking for: ' . $questions[$question]['ANSWER']);
                return true;
            } else {
                if (in_array($guess, $wrong) && !$force) return $bot->add_tellmsg("Quiz: '{$guess}' already done!", $user);
                $wrong[$guess] = $guess;
                if (count($wrong) >= $wrong_count || $force) {
                    $redis->DEL("{$games_key}:running");
                    $bot->add_funmsg("Games Quiz: not solved :!!");
                    $bot->add_funmsg('I was looking for: ' . $questions[$question]['ANSWER']);
                    return true;
                } else {
                    $redis->INCR("{$games_key}:quizzer:versuche");
                    $redis->SET("{$games_key}:quizzer:wrongstr", serialize($wrong));
                    // tipps?
                }
            }
            $bot->add_funmsg('Number of tries ('.$redis->GET("{$games_key}:quizzer:versuche").'): ' . implode(', ', $wrong));
            return true;
        }
    }
    
    $bot->add_funmsg_hook(
    "Easteregg",                                    // command key
    "LouBot_easteregg",                             // callback function
    true,                                           // is a command PRE needet?
    '/^(ostern|osterei|easter|easteregg)$/i',       // optional regex for key
    function ($bot, $data) {
        global $redis;
        if (!$redis->status()) return;
        //to disable return;
        return;
        $eastereggs = array(
            '234:562' => '5€',
            '266:528' => '10€', 
            '277:592' => '5€',  
            '225:535' => '0€ \'Test\'',
            '217:511' => '5€',
            '220:533' => '5€',  
            '286:545' => '10€', 
            '278:517' => '5€',  
            '214:509' => '5€'   
        );
        if ($bot->is_ally_user($data['user']) && !$bot->is_himself($data['user'])) {
            $first_argument = strtolower($data['params'][0]);
            $games_key = "games:alliance:{$bot->ally_id}";
            $solved = $redis->GET("{$games_key}:easteregg:solved");
            $lang = (in_array(strtolower($data['command']), array('!easter','!easteregg'))) ? 'en' : 'de';
            $ttl = $redis->ttl("{$games_key}:easteregg:pause");
            if ($redis->GET("{$games_key}:running")) $bot->add_tellmsg('Games error: another game is already running!', $data['user']);
            else if ($ttl !== -1 && $ttl !== -2) $bot->add_tellmsg('Games Easteregg: '.$redis->GET("{$games_key}:easteregg:pause").' last bet set a timeout!', $data['user']);
            else if ($solved || time() >= 1459184400) {
                $msg['de'] = "Games Easteregg: wurde beendet!";
                $msg['en'] = "Games Easteregg: closed!";
                $bot->add_funmsg($msg[$lang]);
                $_nobody = array('de' => "--", 'en' => "'no one'");
                foreach($eastereggs as $_pos => $easteregg) {
                    if (!$winner = $redis->GET("{$games_key}:easteregg:winner:{$_pos}")) $winner = $_nobody[$lang];
                    $msg['de'] = "Games Easteregg: {$easteregg} ({$_pos}) wurde gefunden von {$winner} (*)";
                    $msg['en'] = "Games Easteregg: {$easteregg} ({$_pos}) solved by {$winner} (*)";
                    $bot->add_funmsg($msg[$lang]);
                }
            } else if (Bot\CotG_Bot::is_string_pos($first_argument)) {
                $pos = Bot\CotG_Bot::get_pos_by_string($first_argument);
                $continent = Bot\CotG_Bot::get_continent_by_pos($pos);
                if ($continent != 52) {
                    $msg['de'] = "Ostersuche Fehler: falscher Kontinent!";
                    $msg['en'] = "Easteregg error: wrong continent!";
                    $bot->add_tellmsg($msg[$lang], $data['user']);
                } else if ($redis->SETNX("{$games_key}:easteregg:tip:{$pos}", $data['user'])) {
                    $redis->SET("{$games_key}:easteregg:pause", $data['user'], 10);
                    //if ($pos == '234:562') {
                    if (in_array($pos, array_keys($eastereggs))) {
                        $redis->SET("{$games_key}:easteregg:winner:{$pos}", $data['user']);
                        $egg = $eastereggs[$pos];
                        $msg['de'] = "Games Easteregg: {$egg} ({$pos}) gefunden von {$data['user']} (*)";
                        $msg['en'] = "Games Easteregg: {$egg} ({$pos}) solved by {$data['user']} (*)";
                        $bot->add_funmsg($msg[$lang]);
                        $count_eggs = count($eastereggs);
                        $count_winner = 0;
                        foreach($eastereggs as $_pos => $easteregg) {
                            if ($redis->GET("{$games_key}:easteregg:winner:{$_pos}")) $count_winner ++;
                        }
                        if ($count_winner == $count_eggs) {
                            $redis->SET("{$games_key}:easteregg:solved", true);
                            $msg['de'] = "Games Easteregg: Alle Ostereier wurden gefunden (*)";
                            $msg['en'] = "Games Easteregg: All eggs found (*)";
                        } else {
                            $msg['de'] = "Games Easteregg: Es gibt noch Ostereier (*)";
                            $msg['en'] = "Games Easteregg: There are more eggs (*)";
                        }
                    } else {
                        $msg['de'] = "Games Easteregg: nicht gefunden :!!";
                        $msg['en'] = "Games Easteregg: not solved :!!";
                    }
                    $bot->add_funmsg($msg[$lang]);
                } else {
                    $redis->SET("{$games_key}:easteregg:pause", $data['user'], 10);
                    $tipper = $redis->get("{$games_key}:easteregg:tip:{$pos}");
                    if ($settler == $data['user']) {
                        $bot->add_tellmsg('Games error: Du hast diese Koordinaten schon getippt!', $data['user']);
                        $bot->add_tellmsg('Games error: You bet already this coordinates!', $data['user']);
                    } else {
                        $msg['de'] = "Games error: {$tipper} hat diese Koordinaten schon getippt!";
                        $msg['en'] = "Games error: {$tipper} bet already this coordinates!";
                    }
                    $bot->add_tellmsg($msg[$lang], $data['user']);
                }
            } else {
                $msg['de'] = "Ostersuche Fehler: falsche Koordinaten";
                $msg['en'] = "Easteregg error: wrong coordinates";
                $first_argument = ($first_argument != '') ? $first_argument : 'xxx:yyy';
                $bot->add_tellmsg($msg[$lang] . ' ('.$first_argument.')!', $data['user']);
            }
        } else $bot->add_tellmsg("Ne Ne Ne!", $data['user']);
    }, 'games');
    
    if(!function_exists('generate_regexp')) {
        function generate_regexp($string) {
            preg_match_all('/(.*)#(.*)#(.*)/i', strtolower($string), $matches, PREG_SET_ORDER);
            if (!empty($matches)) {
                $regex = "(";
                if ($matches[0][1] != '') $regex .= "(" . $matches[0][1] . ")?";
                $regex .= "(" . $matches[0][2] . ")";
                if ($matches[0][3] != '') $regex .= "(" . $matches[0][3] . ")?";
                $regex .= ")";
                } else {
                $regex = strtolower($string);
            }
            $regex = str_replace('ä', '(ä|ae)', $regex);
            $regex = str_replace('ü', '(ü|ue)', $regex);
            $regex = str_replace('ö', '(ö|oe)', $regex);
            $regex = str_replace('ß', '(ß|ss|s)', $regex);
            return $regex;
        }
    }    