<?php

namespace KHR\React\Curl;

use \React\Promise\Deferred;
use \React\Promise\Promise;

class Curl {

    /**
     * @var \React\EventLoop\LoopInterface
     */
    public $loop;

    /**
     * @var \React\EventLoop\Timer\TimerInterface
     */
    public $loop_timer;


    /**
     * @var \MCurl\Client
     */
    public $client;

    /**
     * Timeout: check curl resource
     * @var float
     */
    public $timeout = 0.01;



    public function __construct($loop) {
        $this->loop = $loop;
        $this->client = new \MCurl\Client();
        $this->client->isSelect(false);
        $this->client->setClassResult('\\KHR\\React\Curl\\Result');
    }

    /**
     * @param $url
     * @param array $opts
     * @return Promise
     */
    public function get($url, $opts = [], $params = []) {
        $opts[CURLOPT_URL] = $url;
        $promise = $this->add($opts, $params);
        $this->run();
        return $promise;
    }

    /**
     * @param $url
     * @param array $data
     * @param array $opts
     * @return Promise
     */
    public function post($url, $data = [], $opts = []) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($data);
        return $this->get($url, $opts);
    }

    /**
     * @param $opts
     * @param array $params
     * @return Promise
     */
    public function add($opts, $params = []) {
        $params['__deferred'] = $deferred = new Deferred();
        $this->client->add($opts, $params);
        return $deferred->promise();
    }

    public function run() {
        $client = $this->client;
        $client->run();

        while($client->has()) {
            /**
             * @var Result $result
             */
            $result = $client->next();
            $deferred = $result->shiftDeferred();

            if (!$result->hasError()) {
                $deferred->resolve($result);
            } else {
                $deferred->reject(new Exception($result));
            }
        }

        if (!isset($this->loop_timer)) {
            $this->loop_timer = $this->loop->addPeriodicTimer($this->timeout, function() {
                $this->run();
                if (!($this->client->run() || $this->client->has())) {
                    $this->loop_timer->cancel();
                    $this->loop_timer = null;
                }
            });
        }
    }
}
