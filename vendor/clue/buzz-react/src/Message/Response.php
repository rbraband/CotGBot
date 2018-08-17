<?php

namespace Clue\React\Buzz\Message;

use Clue\React\Buzz\Message\Headers;
use Zend\Stdlib\ErrorHandler;

class Response implements Message
{
    private $protocol;
    private $code;
    private $reasonPhrase;
    private $headers;
    private $body;

    public function __construct($protocol, $code, $reasonPhrase, $headers = array(), $body = '')
    {
        if (!($headers instanceof Headers)) {
            $headers = new Headers($headers);
        }
        if (!($body instanceof Body)) {
            $body = new Body($body);
        }

        $this->protocol = $protocol;
        $this->code = $code;
        $this->reasonPhrase = $reasonPhrase;
        $this->headers = $headers;
        $this->body = $body;
    }

    public function getHttpVersion()
    {
       return $this->protocol;
    }

    public function getCode()
    {
        return $this->code;
    }

    public function getReasonPhrase()
    {
        return $this->reasonPhrase;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getHeader($name)
    {
        return $this->headers->getHeaderValue($name);
    }
    
    public function getCookies()
    {
        return $this->headers->getHeaderValues('Set-Cookie');
    }

    public function getBody()
    {
        $body = $this->body;
        // Transfer-Encoding
        $transferEncoding = $this->getHeader('Transfer-Encoding');
        if (strtolower($transferEncoding) == 'chunked') {
            $body = $this->decodeChunkedBody($body);
        }
        // Content-Encoding
        $contentEncoding = $this->getHeader('Content-Encoding');
        if (strtolower($contentEncoding) == 'gzip') {
            $body = $this->decodeGzip($body);
        } else if (strtolower($contentEncoding) == 'deflate') {
            $body = $this->decodeDeflate($body);
        }
        return $body;
    }
    
    /**
     * Decode a "chunked" transfer-encoded body and return the decoded text
     *
     * @param  string $body
     * @return string
     * @throws RuntimeException
     */
    protected function decodeChunkedBody($body)
    {
        $decBody = '';

        while (trim($body)) {
            if (! preg_match("/^([\da-fA-F]+)[^\r\n]*\r\n/sm", $body, $m)) {
                throw new \RuntimeException(
                    "Error parsing body - doesn't seem to be a chunked message"
                );
            }

            $length   = hexdec(trim($m[1]));
            $cut      = strlen($m[0]);
            $decBody .= substr($body, $cut, $length);
            $body     = substr($body, $cut + $length + 2);
        }

        return $decBody;
    }
    
    /**
     * Decode a gzip encoded message (when Content-encoding = gzip)
     *
     * Currently requires PHP with zlib support
     *
     * @param  string $body
     * @return string
     * @throws RuntimeException
     */
    protected function decodeGzip($body)
    {
        if (function_exists('gzdecode ')) {
            return gzdecode($body);
        }
        else if (!function_exists('gzinflate')) {
            throw new \RuntimeException(
                'zlib extension is required in order to decode "gzip" encoding'
            );
        }
                
        ErrorHandler::start();
        $return = gzinflate(substr($body, 10, -8));
        $test = ErrorHandler::stop();
        if ($test) {
            throw new \RuntimeException(
                'Error occurred during gzip inflation',
                0,
                $test
            );
        }
        return $return;
    }

    /**
     * Decode a zlib deflated message (when Content-encoding = deflate)
     *
     * Currently requires PHP with zlib support
     *
     * @param  string $body
     * @return string
     * @throws \RuntimeException
     */
    protected function decodeDeflate($body)
    {
        if (!function_exists('gzuncompress')) {
            throw new \RuntimeException(
                'zlib extension is required in order to decode "deflate" encoding'
            );
        }

        /**
         * Some servers (IIS ?) send a broken deflate response, without the
         * RFC-required zlib header.
         *
         * We try to detect the zlib header, and if it does not exist we
         * teat the body is plain DEFLATE content.
         *
         * This method was adapted from PEAR HTTP_Request2 by (c) Alexey Borzov
         *
         * @link http://framework.zend.com/issues/browse/ZF-6040
         */
        $zlibHeader = unpack('n', substr($body, 0, 2));

        if ($zlibHeader[1] % 31 == 0) {
            return gzuncompress($body);
        }
        
        else if (!function_exists('gzinflate')) {
            throw new \RuntimeException(
                'zlib extension is required in order to decode "gzip" encoding'
            );
        }
        return gzinflate($body);

    }
}
