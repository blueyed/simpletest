<?php
    // $Id$
    
    if (!defined("SIMPLE_TEST")) {
        define("SIMPLE_TEST", "../");
    }
    require_once(SIMPLE_TEST . 'http.php');
    Mock::generate("SimpleSocket");

    class HttpTestCase extends UnitTestCase {
        function HttpTestCase() {
            $this->UnitTestCase();
        }
        function testReadingBadConnection() {
            $request = new SimpleHttpRequest("http://a.bad.page/");
            $socket = &new MockSimpleSocket($this);
            $socket->setReturnValue("isError", true);
            $this->assertFalse($request->fetch(&$socket));
        }
        function testReadingGoodConnection() {
            $request = new SimpleHttpRequest("http://a.valid.page/and/path");
            $socket = &new MockSimpleSocket($this);
            $socket->setReturnValue("isError", false);
            $socket->setExpectedArgumentsSequence(0, "write", array("GET a.valid.page/and/path HTTP/1.0\r\n"));
            $socket->setExpectedArgumentsSequence(1, "write", array("Host: localhost\r\n"));
            $socket->setExpectedArgumentsSequence(2, "write", array("Connection: close\r\n"));
            $socket->setExpectedArgumentsSequence(3, "write", array("\r\n"));
            $socket->setExpectedCallCount("write", 4);
            $this->assertIsA($request->fetch(&$socket), "SimpleHttpResponse");
            $socket->tally();
        }
        function testCookieWriting() {
            $request = new SimpleHttpRequest("http://a.valid.page/and/path");
            $request->setCookies(array("a" => "A"));
            $socket = &new MockSimpleSocket($this);
            $socket->setReturnValue("isError", false);
            $socket->setExpectedArgumentsSequence(0, "write", array("GET a.valid.page/and/path HTTP/1.0\r\n"));
            $socket->setExpectedArgumentsSequence(1, "write", array("Host: localhost\r\n"));
            $socket->setExpectedArgumentsSequence(2, "write", array("Cookie: a=A\r\n"));
            $socket->setExpectedArgumentsSequence(3, "write", array("Connection: close\r\n"));
            $socket->setExpectedArgumentsSequence(4, "write", array("\r\n"));
            $socket->setExpectedCallCount("write", 5);
            $this->assertIsA($request->fetch(&$socket), "SimpleHttpResponse");
            $socket->tally();
        }
    }
    
    class LiveHttpTestCase extends UnitTestCase {
        function LiveHttpTestCase() {
            $this->UnitTestCase();
        }
        function testRealPageFetch() {
            $http = new SimpleHttpRequest("www.lastcraft.com/test/network_confirm.php");
            $this->assertIsA($http->fetch(), "SimpleHttpResponse");
        }
    }
?>