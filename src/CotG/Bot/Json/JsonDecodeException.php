<?php
    
    namespace CotG\Bot\Json;
    
    /**
    * Exception, die geworfen wird, wenn JSON Decode fehlschlägt
    */
    class JsonDecodeException extends \Exception {
        function __construct($strMessage, $code = 0){
            parent::__construct($strMessage, $code);
        }
    }