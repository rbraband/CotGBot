<?php

use React\EventLoop\Factory;
use Clue\React\Buzz\Browser;

class FunctionalBrowserTest extends TestCase
{
    private $loop;
    private $browser;

    /** base url to the httpbin service  **/
    private $base = 'http://httpbin.org/';

    public function setUp()
    {
        $this->loop = Factory::create();
        $this->browser = new Browser($this->loop);
    }

    public function testSimpleRequest()
    {
        $this->expectPromiseResolve($this->browser->get($this->base . 'get'));

        $this->loop->run();
    }

    public function testRedirectRequestRelative()
    {
        $this->expectPromiseResolve($this->browser->get($this->base . 'redirect-to?url=get'));

        $this->loop->run();
    }

    public function testRedirectRequestAbsolute()
    {
        $this->expectPromiseResolve($this->browser->get($this->base . 'redirect-to?url=' . urlencode($this->base . 'get')));

        $this->loop->run();
    }

    public function testNotFollowingRedirectsResolvesWithRedirectResult()
    {
        $browser = $this->browser->withOptions(array('followRedirects' => false));

        $this->expectPromiseResolve($browser->get($this->base . 'redirect/3'));

        $this->loop->run();
    }

    public function testRejectingRedirectsRejects()
    {
        $browser = $this->browser->withOptions(array('maxRedirects' => 0));

        $this->expectPromiseReject($browser->get($this->base . 'redirect/3'));

        $this->loop->run();
    }

    public function testInvalidPort()
    {
        $this->expectPromiseReject($this->browser->get('http://www.google.com:443/'));

        $this->loop->run();
    }

    public function testErrorStatusCodeRejectsWithResponseException()
    {
        $that = $this;
        $this->expectPromiseReject($this->browser->get($this->base . 'status/404'))->then(null, function ($e) use ($that) {
            $that->assertInstanceOf('Clue\Buzz\React\Message\ResponseException', $e);
            $that->assertEquals(404, $e->getCode());

            $that->assertInstanceOf('Clue\Buzz\React\Message\Response', $e->getResponse());
            $that->assertEquals(404, $e->getResponse()->getCode());
        });

        $this->loop->run();
    }
}
