<?php
global $bot;
$bot->add_category('toys',          array('humanice' => true,  'fuzzy'    => true,  'spamsafe' => true),  PUBLICY);
$bot->add_category('toys_humanice', array('humanice' => true,  'fuzzy'    => false, 'spamsafe' => false), PUBLICY);
$bot->add_category('toys_spam',     array('humanice' => false, 'fuzzy'    => false, 'spamsafe' => true),  PUBLICY);
$bot->add_category('toys_fuzzy',    array('humanice' => false, 'fuzzy'    => true,  'spamsafe' => true),  PUBLICY) ;

$bot->add_privmsg_hook("Echo",                // command key
                       "CotGBot_echo",        // callback function
                       true,                  // is a command PRE needet?
                       '/^echo$/i',           // optional regex for key
function ($bot, $data) {
  if($bot->is_global_user($data['user'])) {
    $bot->add_privmsg(implode(' ', $data['params']), $data['user']);
  } else $bot->add_privmsg("Ne Ne Ne!", $data['user']);
}, 'toys_spam');

$bot->add_allymsg_hook("Hello",                  // command key
                       "LouBot_hello",           // callback function
                       false,                    // is a command PRE needet?
                       "/^(hi|hallo|hello|moin|moin moin|tach|tach auch|tachen|nabend|abend|huhu)[,]? ({$bot->ally_name}|{$bot->ally_shortname}|Ally)$/i", // optional regex for key
function ($bot, $data) {
  $text_i18n['de'][] = 'Hi ';
  $text_i18n['de'][] = 'Hallo ';
  $text_i18n['de'][] = 'Moin Moin ';
  
  $text_i18n['en'][] = 'Hi ';
  $text_i18n['en'][] = 'Hello ';
  $text_i18n['en'][] = 'Hi my friend ';
  
  $nick = $data['params'][0];
  $text = (!empty($text_i18n[BOT_LANG])) ? $text_i18n[BOT_LANG] : $text_i18n['de'];
    
  shuffle($text);
  $rand_key = array_rand($text, 1);
  if (!$bot->is_himself($data['user']))
    $bot->add_allymsg($text[$rand_key] . ucfirst(strtolower($data['user'])) . ' :)');
}, 'toys_humanice'); // explicitly

$bot->add_allymsg_hook("ByeBye",                 // command key
                       "LouBot_bye",             // callback function
                       false,                    // is a command PRE needet?
                       "/^(bb|bye|byebye|goodbye|tschüss|tschau|n8|n8ti|gn8|n8t)[,]? ({$bot->ally_name}|{$bot->ally_shortname})+$/i", // optional regex for key
function ($bot, $data) {
  $text_i18n['de'][] = 'bb ';
  $text_i18n['de'][] = 'tschau ';
  $text_i18n['de'][] = 'n8ti ';
  $text_i18n['de'][] = 'n8 ';
  
  $text_i18n['en'][] = 'bb ';
  $text_i18n['en'][] = 'cya ';
  $text_i18n['en'][] = 'sleep well ';
  $text_i18n['en'][] = 'n8 ';
  
  $nick = $data['params'][0];
  $text = (!empty($text_i18n[BOT_LANG])) ? $text_i18n[BOT_LANG] : $text_i18n['de'];
    
  shuffle($text);
  $rand_key = array_rand($text, 1);
  if (!$bot->is_himself($data['user']))
    $bot->add_allymsg($text[$rand_key] . ucfirst(strtolower($bot->get_random_nick($data['user']))) . ' :)');
}, 'toys_humanice'); // explicitly

$bot->add_allymsg_hook("Re",                  // command key
                       "LouBot_re",           // callback function
                       false,                 // is a command PRE needet?
                       '/^re[,]?$/i',         // optional regex for key
function ($bot, $data) {
  if (!$bot->is_himself($data['user']))
    $bot->add_allymsg("wb " . ucfirst(strtolower($data['user'])) . ' :)');
}, 'toys_humanice');

$bot->add_allymsg_hook("TGIF",                  // command key
                       "LouBot_tgif",           // callback function
                       false,                 // is a command PRE needet?
                       '/^TGIF$/i',         // optional regex for key
function ($bot, $data) {
  if (!$bot->is_himself($data['user']))
    $bot->add_allymsg('Thank God Its Friday');
}, 'toys_humanice');

$bot->add_allymsg_hook("Zitat",                  // command key
                       "LouBot_phrases",         // callback function
                       true,                     // is a command PRE needet?
                       '/^(zitate|zitat|phrase)$/i',   // optional regex for key
function ($bot, $data) {
  global $phrases;
  if (!$bot->is_himself($data['user'])) {
    $lang = (in_array(strtolower($data['params'][0]), array('en','de'))) ? strtolower($data['params'][0]) : BOT_LANG;
    if (empty($phrases[$lang])) {
      $lines = file(PERM_DATA.'phrases.' . $lang, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      foreach ($lines as $line_num => $line) {
        if ($line[0] != '#') $phrases[$lang][$line_num] = htmlspecialchars($line);
      }
    }
    $_phrases = $phrases[$lang];
    shuffle($_phrases);
    $rand_key = array_rand($_phrases, 1);
    $bot->add_allymsg($_phrases[$rand_key]);
  }
}, 'toys_spam');

$bot->add_allymsg_hook("Sun-Tzu",                  // command key
                       "LouBot_suntzu",           // callback function
                       true,                       // is a command PRE needet?
                       '/^(suntzu|sun tzu|sun-tzu|sun_tzu)$/i',   // optional regex for key
function ($bot, $data) {
  global $suntzu;
  if (!$bot->is_himself($data['user'])) {
    $lang = (in_array(strtolower($data['params'][0]), array('en','de'))) ? strtolower($data['params'][0]) : BOT_LANG;
    if (empty($suntzu[$lang])) {
      $lines = file(PERM_DATA.'sun-tzu.' . $lang, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
      foreach ($lines as $line_num => $line) {
        if ($line[0] != '#') $suntzu[$lang][$line_num] = htmlspecialchars($line);
      }
    }
    $_suntzu = $suntzu[$lang];
    shuffle($_suntzu);
    $rand_key = array_rand($_suntzu, 1);
    $bot->add_allymsg($_suntzu[$rand_key]);
  }
}, 'toys_spam');
