<?php
    
    namespace CotG\Bot\Json;
    
    use CotG\Bot\Log;
    
    class Json { 
        
        public static function Encode($obj) {
            return json_encode($obj);
        }
        
        public static function getErrorMessage($result) {
            $messages = array(
            JSON_ERROR_NONE           => 'No error has occurred',
            JSON_ERROR_DEPTH          => 'The maximum stack depth has been exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
            JSON_ERROR_CTRL_CHAR      => 'Control character error, possibly incorrectly encoded',
            JSON_ERROR_SYNTAX         => 'Syntax error',
            JSON_ERROR_UTF8           => 'Malformed UTF-8 characters, possibly incorrectly encoded'
            );
            return $messages[$result];
        }
        
        public static function Decode($json, $toAssoc = false) {
            
            //Remove UTF-8 BOM if present, json_decode() does not like it.
            if(substr($json, 0, 3) == pack("CCC", 0xEF, 0xBB, 0xBF)) $json = substr($json, 3);
            
            try {
                $result = json_decode(trim($json), $toAssoc);
                $state = json_last_error();
                if ($state !== JSON_ERROR_NONE) {
                    $error = Json::getErrorMessage($state);
                    throw new JsonDecodeException("JSON Error ({$state}): {$error}");       
                }
                } catch (JsonDecodeException $e){
                Log\Logger::getInstance()->err($e->getMessage());
                return false;
            }
            
            return $result;
        }
    }    