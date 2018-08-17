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
    
    $bot->add_category('system', array(), PRIVACY);
    
    // crons
        
    // callbacks
    