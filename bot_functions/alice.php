<?php
use CotG\Bot\Log;

global $bot;
$bot->add_category('alice', array('humanice' => true), PUBLICY);
// crons

// callbacks
$bot->add_allymsg_hook("Alice",                 // command key
                       "LouBot_alice",          // callback function
                       false,                   // is a command PRE needet?
                       "/^([@]?{$bot->bot_user_name}[,.?\s]+(.*)|(.*)[,.\s]+{$bot->bot_user_name}[ ]?[.!?:\/()DP]{0,})$/i",  // optional regex for key
    function ($bot, $data) {
        global $redis;

        if (!$bot->is_himself($data['user'])) {
            if (!$redis->status() || ALICEID == '' || ALICEID == 'youralice_id') return $bot->add_allymsg(magic_8ball(BOT_LANG)); // fallback
            $key = "alice:spamcheck:LouBot_alice:{$data['user']}";
            $bot->log(REDIS_NAMESPACE . "{$key} TTL: {$redis->ttl($key)}");
            if ($redis->ttl($key) === -1 || $redis->ttl($key) === -2) {
                
                $bot->log("NoSPAM by " . $data['user']);
                
                $redis->set($key, 0, ALICETTL);
                $request = array(
                    'botid'  => ALICEID,
                    'input'  => preg_replace('/' . $bot->bot_user_name . '/i', '', $data['message']), //str_replace($bot->bot_user_name , '' , $data['message']),
                    'custid' => $data['user']
                );
                
                $response        = alice_call($request, $data, function($data, $response) {
                    global $bot;
                    
                    $reply = false;
                    $lang = $bot->get_lang($data['user']);
                    $anrede[] = ucfirst(strtolower($bot->get_random_nick($data['user']))) . ', ';
                    $anrede[] = '@' . ucfirst(strtolower($bot->get_random_nick($data['user']))) . ' - ';
                    $anrede[] = '... ';
                    $anrede[] = '';
                    shuffle($anrede);
                    $rand_key_anrede = array_rand($anrede, 1);
                
                    if ($response) {
                        $bot->log("CotG -> get response from ALICE");
                        $xml = @simplexml_load_string($response);
                        if ($xml) {
                            $reply = $xml->that;
                        } else {
                            $bot->error('XML Error: cannot xpath Data!');
                            foreach (libxml_get_errors() as $error) {
                                $bot->debug($error->message);
                            }
                            $message = magic_8ball($lang);
                        }
                        if ($reply) {
                            $array_from_to = [
                                '<botmaster></botmaster>' => BOT_OWNER,
                                '<setname></setname>' => $bot->get_random_nick($data['user']),
                                'ALICE' => 'me',
                                'by Lauren' => 'by ' . BOT_OWNER,
                                'Lauren' => BOT_OWNER,
                                '<getname></getname>' => 'I dont know...',
                                '<size></size>' => '42',
                                ', .' => ', ' . $data['user'] . '.',
                                '<br>' => ' ... ',
                                '&#61;'=> '=',
                                '&#46;'=> '.',
                                '&#34;'=> '"'
                            ];
                            $message = strtr($reply, $array_from_to);
                        } else if ($xml->message) {
                            $bot->error('ALICE Error: ' . $xml->message);
                            $message = magic_8ball($lang);
                        }
                    } else {
                        $bot->error('ALICE Error: cannot receive Data!');
                        $message = magic_8ball($lang);
                    }
                    if (preg_match('/' . $data['user'] . '/i', $reply) === 1) $rand_key_anrede = null;

                    return $bot->add_allymsg(($anrede[$rand_key_anrede] != '') ? $anrede[$rand_key_anrede] . lcfirst($message) : $message);
                });
            } else {
                $incr = $redis->incr($key) * ALICETTL;
                $redis->EXPIRE($key, $incr);
            }
        }
        _destroy($data);
        return false;
    }, 'alice');

if (!function_exists('alice_call')) {
    function alice_call($request, $data, $callback) {
        global $bot;
        
        $curl = new \KHR\React\Curl\Curl($bot->getLoop());
        $curl->client->setCurlOption([
            CURLOPT_AUTOREFERER => true,
            CURLOPT_VERBOSE => $bot->isDebug(),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FRESH_CONNECT => 1,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_TIMEOUT => ALICETIMEOUT,
            CURLOPT_ENCODING => 'gzip,deflate',
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:34.0) Gecko/20100101 Firefox/34.0',
        ]);
        $url = "https://www.pandorabots.com/pandora/talk-xml";

        $curl->post($url, $request, $opts)->then(function (\KHR\React\Curl\Result $result) use($data, $callback) {
            global $bot;
            
            $response = $result->getBody();
            
            if (!empty($response)) {
                if ($bot->isDebug()) {
                    $bot->debug("Got ALICE data!");
                    $bot->debug(print_r($response, true));
                }
            } else {
                $bot->log("No ALICE data received!", Log\Logger::WARN);
            }
            call_user_func($callback, $data, $response);
            _destroy($result, $response);
        });
        _destroy($url, $request, $opts);
    }
}

if (!function_exists('magic_8ball')) {
    function magic_8ball($lang) {
        // de
        $text_i18n['de'][] = 'Da antworte ich lieber nicht, versuche es erneut :|';
        $text_i18n['de'][] = 'Darauf reagier ich gar nicht...';
        $text_i18n['de'][] = 'Besser nix sagen *hmpf*';
        $text_i18n['de'][] = 'Konzentriere dich auf das was du eigentlich Fragen wolltest!';
        $text_i18n['de'][] = 'Wie ich es sehe, ja :)';
        $text_i18n['de'][] = 'Es ist sicher B-)';
        $text_i18n['de'][] = 'Ja - auf jeden Fall.';
        $text_i18n['de'][] = 'Höchstwahrscheinlich';
        $text_i18n['de'][] = 'Empfehlenswert ;)';
        $text_i18n['de'][] = 'Die Zeichen deuten auf - [b]ja[/b]';
        $text_i18n['de'][] = 'Ohne Zweifel :-7';
        $text_i18n['de'][] = 'Ohne Mich :-7';
        $text_i18n['de'][] = 'Da kannst du dich drauf verlassen :)';
        $text_i18n['de'][] = 'Es ist entschieden, so oder so *gg*';
        $text_i18n['de'][] = 'Da verlässt sich keiner drauf =P';
        $text_i18n['de'][] = 'Meine Antwort ist ... [i]NULL[/i]';
        $text_i18n['de'][] = 'Meine Quellen sagen nein!';
        $text_i18n['de'][] = 'nicht so empfehlenswert :/';
        $text_i18n['de'][] = 'Sehr zweifelhaft =O';
        $text_i18n['de'][] = 'glaub ich nicht ^^';
        $text_i18n['de'][] = 'Wo ist denn das Problem?';
        $text_i18n['de'][] = 'Wie jetzt?';
        $text_i18n['de'][] = 'Dir antworte ich nicht!';
        $text_i18n['de'][] = 'Sorry, jetzt muss ich gerade was anderes tun...';
        // en
        $text_i18n['en'][] = 'As I see it, yes.';
        $text_i18n['en'][] = 'Ask again later.';
        $text_i18n['en'][] = 'Better not tell you now.';
        $text_i18n['en'][] = 'Cannot predict now.';
        $text_i18n['en'][] = 'Concentrate and ask again.';
        $text_i18n['en'][] = 'Don\'t count on it.';
        $text_i18n['en'][] = 'It is certain.';
        $text_i18n['en'][] = 'It is decidedly so.';
        $text_i18n['en'][] = 'Most likely.';
        $text_i18n['en'][] = 'My reply is no.';
        $text_i18n['en'][] = 'My sources say no.';
        $text_i18n['en'][] = 'Outlook good.';
        $text_i18n['en'][] = 'Outlook not so good.';
        $text_i18n['en'][] = 'Reply hazy, try again.';
        $text_i18n['en'][] = 'Signs point to yes.';
        $text_i18n['en'][] = 'return Very doubtful.';
        $text_i18n['en'][] = 'Without a doubt.';
        $text_i18n['en'][] = 'Yes.';
        $text_i18n['en'][] = 'Yes - definitely.';
        $text_i18n['en'][] = 'You may rely on it.';
        
        $text = (!empty($text_i18n[$lang])) ? $text_i18n[$lang] : $text_i18n['de'];
        shuffle($text);
        $rand_key = array_rand($text, 1);

        return $text[$rand_key];
    }
}