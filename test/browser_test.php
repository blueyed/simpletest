<?php
    // $Id$
    
    if (!defined("SIMPLE_TEST")) {
        define("SIMPLE_TEST", "../");
    }
    require_once(SIMPLE_TEST . 'browser.php');
    require_once(SIMPLE_TEST . 'http.php');
    Mock::generate("UnitTestCase");
    Mock::generate("SimpleHttpRequest");
    Mock::generate("SimpleHttpResponse");

    class TestOfCookieJar extends UnitTestCase {
        function TestOfCookieJar() {
            $this->UnitTestCase();
        }
        function testAddCookie() {
            $jar = new CookieJar();
            $jar->setCookie(new SimpleCookie("a", "A"));
            $cookies = $jar->getValidCookies();
            $this->assertEqual(count($cookies), 1);
            $this->assertEqual($cookies[0]->getValue(), "A");
        }
        function testHostFilter() {
            $jar = new CookieJar();
            $cookie = new SimpleCookie("a", "A");
            $cookie->setHost("my-host.com");
            $jar->setCookie($cookie);
            $cookie = new SimpleCookie("b", "B");
            $cookie->setHost("another-host.com");
            $jar->setCookie($cookie);
            $cookie = new SimpleCookie("c", "C");
            $jar->setCookie($cookie);
            $cookies = $jar->getValidCookies("my-host.com");
            $this->assertEqual(count($cookies), 2);
            $this->assertEqual($cookies[0]->getValue(), "A");
            $this->assertEqual($cookies[1]->getValue(), "C");
            $this->assertEqual(count($jar->getValidCookies("another-host.com")), 2);
            $this->assertEqual(count($jar->getValidCookies("www.another-host.com")), 2);
            $this->assertEqual(count($jar->getValidCookies("new-host.org")), 1);
            $this->assertEqual(count($jar->getValidCookies()), 3);
        }
        function testPathFilter() {
            $jar = new CookieJar();
            $jar->setCookie(new SimpleCookie("a", "A", "/path/"));
            $this->assertEqual(count($jar->getValidCookies(false, "/")), 0);
            $this->assertEqual(count($jar->getValidCookies(false, "/elsewhere")), 0);
            $this->assertEqual(count($jar->getValidCookies(false, "/path/")), 1);
            $this->assertEqual(count($jar->getValidCookies(false, "/path")), 1);
            $this->assertEqual(count($jar->getValidCookies(false, "/pa")), 0);
            $this->assertEqual(count($jar->getValidCookies(false, "/path/here/")), 1);
        }
        function testPathFilterDeeply() {
            $jar = new CookieJar();
            $jar->setCookie(new SimpleCookie("a", "A", "/path/more_path/"));
            $this->assertEqual(count($jar->getValidCookies(false, "/path/")), 0);
            $this->assertEqual(count($jar->getValidCookies(false, "/path")), 0);
            $this->assertEqual(count($jar->getValidCookies(false, "/pa")), 0);
            $this->assertEqual(count($jar->getValidCookies(false, "/path/more_path/")), 1);
            $this->assertEqual(count($jar->getValidCookies(false, "/path/more_path/and_more")), 1);
            $this->assertEqual(count($jar->getValidCookies(false, "/path/not_here/")), 0);
        }
        function testMultipleCookieWithDifferentPaths() {
            $jar = new CookieJar();
            $jar->setCookie(new SimpleCookie("a", "abc", "/"));
            $jar->setCookie(new SimpleCookie("a", "123", "/path/here/"));
            $cookies = $jar->getValidCookies("my-host.com", "/");
            $this->assertEqual($cookies[0]->getPath(), "/");
            $cookies = $jar->getValidCookies("my-host.com", "/path/");
            $this->assertEqual($cookies[0]->getPath(), "/");
            $cookies = $jar->getValidCookies("my-host.com", "/path/here");
            $this->assertEqual($cookies[0]->getPath(), "/");
            $this->assertEqual($cookies[1]->getPath(), "/path/here/");
            $cookies = $jar->getValidCookies("my-host.com", "/path/here/there");
            $this->assertEqual($cookies[0]->getPath(), "/");
            $this->assertEqual($cookies[1]->getPath(), "/path/here/");
        }
        function testOverwrite() {
            $jar = new CookieJar();
            $jar->setCookie(new SimpleCookie("a", "abc", "/"));
            $jar->setCookie(new SimpleCookie("a", "cde", "/"));
            $cookies = $jar->getValidCookies();
            $this->assertIdentical($cookies[0]->getValue(), "cde");
        }
        function testClearSessionCookies() {
            $jar = new CookieJar();
            $jar->setCookie(new SimpleCookie("a", "A", "/"));
            $jar->restartSession();
            $this->assertEqual(count($jar->getValidCookies(false, "/")), 0);
        }
        function testExpiryFilter() {
            $jar = new CookieJar();
            $jar->setCookie(new SimpleCookie("a", "A", "/", "Wed, 25-Dec-02 04:24:20 GMT"));
            $jar->restartSession("Wed, 25-Dec-02 04:24:19 GMT");
            $this->assertEqual(count($jar->getValidCookies(false, "/")), 1);
            $jar->restartSession("Wed, 25-Dec-02 04:24:21 GMT");
            $this->assertEqual(count($jar->getValidCookies(false, "/")), 0);
        }
        function testCookieClearing() {
            $jar = new CookieJar();
            $jar->setCookie(new SimpleCookie("a", "abc", "/"));
            $jar->setCookie(new SimpleCookie("a", "", "/"));
            $this->assertEqual(count($cookies = $jar->getValidCookies(false, "/")), 1);
            $this->assertIdentical($cookies[0]->getValue(), "");
        }
        function testCookieClearByDate() {
            $jar = new CookieJar();
            $jar->setCookie(new SimpleCookie("a", "abc", "/", "Wed, 25-Dec-02 04:24:21 GMT"));
            $jar->setCookie(new SimpleCookie("a", "def", "/", "Wed, 25-Dec-02 04:24:19 GMT"));
            $cookies = $jar->getValidCookies(false, "/");
            $this->assertIdentical($cookies[0]->getValue(), "def");
            $jar->restartSession("Wed, 25-Dec-02 04:24:20 GMT");
            $this->assertEqual(count($jar->getValidCookies(false, "/")), 0);
        }
    }
    
    class TestOfExpandomaticUrl extends UnitTestCase {
        function TestOfExpandomaticUrl() {
            $this->UnitTestCase();
        }
        function testSetBase() {
            $response = &new MockSimpleHttpResponse($this);
            $response->setReturnValue("isError", false);
            $response->setReturnValue("getNewCookies", array());
            $request = &new MockSimpleHttpRequest($this);
            $request->setReturnReference("fetch", $response);
            $browser = &new SimpleBrowser();
            $browser->get("http://this.com/this/path/page.html", false, &$request);
            $this->assertEqual(
                    $browser->getBaseUrl(),
                    "http://this.com/this/path/");
        }
    }
    
    class TestOfBrowserCookies extends UnitTestCase {
        function TestOfBrowserCookies() {
            $this->UnitTestCase();
        }
        function &_createStandardResponse() {
            $response = &new MockSimpleHttpResponse($this);
            $response->setReturnValue("isError", false);
            $response->setReturnValue("getNewCookies", array());
            $response->setReturnValue("getContent", "stuff");
            return $response;
        }
        function &_createCookieSite($cookies) {
            $response = &new MockSimpleHttpResponse($this);
            $response->setReturnValue("isError", false);
            $response->setReturnValue("getContent", "stuff");
            $response->setReturnValue("getNewCookies", $cookies);
            $request = &new MockSimpleHttpRequest($this);
            $request->setReturnReference("fetch", $response);
            return $request;
        }
        function testSendCookie() {
            $request = &new MockSimpleHttpRequest($this);
            $request->setReturnReference("fetch", $this->_createStandardResponse());
            $request->expectArguments("setCookie", array(new SimpleCookie("a", "A")));
            $request->expectCallCount("setCookie", 1);
            $browser = &new SimpleBrowser();
            $browser->setCookie("a", "A");
            $response = $browser->fetchResponse(
                    new SimpleUrl("http://this.com/this/path/page.html"),
                    $request);
            $this->assertEqual($response->getContent(), "stuff");
            $request->tally();
        }
        function testReceiveExistingCookie() {
            $request = &$this->_createCookieSite(array(new SimpleCookie("a", "AAAA", "this/path/")));
            $browser = &new SimpleBrowser();
            $browser->setCookie("a", "A");
            $browser->fetchResponse(
                    new SimpleUrl("http://this.host/this/path/page.html"),
                    $request);
            $this->assertEqual($browser->getCookieValue("this.com", "this/path/", "a"), "AAAA");
        }
        function testClearCookie() {
            $request = &$this->_createCookieSite(array(new SimpleCookie(
                    "a",
                    "",
                    "this/path/",
                    "Wed, 25-Dec-02 04:24:19 GMT")));
            $browser = &new SimpleBrowser();
            $browser->setCookie("a", "A", "this/path/", "Wed, 25-Dec-02 04:24:21 GMT");
            $this->assertEqual(
                    $browser->get("http://this.com/this/path/page.html", false, &$request),
                    "stuff");
            $this->assertIdentical(
                    $browser->getCookieValue("this.com", "this/path/", "a"),
                    "");
            $browser->restartSession("Wed, 25-Dec-02 04:24:20 GMT");
            $this->assertIdentical(
                    $browser->getCookieValue("this.com", "this/path/", "a"),
                    false);
        }
        function testReadingCookies() {
            $browser = &new SimpleBrowser();
            $this->assertNull($browser->getBaseCookieValue("a"));
            $browser->setCookie("b", "BBB", "this.com", "this/path/");
            $request = &$this->_createCookieSite(array(new SimpleCookie("a", "AAA", "this/path/")));
            $browser->get("http://this.com/this/path/page.html", false, &$request);
            $this->assertEqual($browser->getBaseCookieValue("a"), "AAA");
            $this->assertEqual($browser->getBaseCookieValue("b"), "BBB");
        }
    }
    
    Mock::generatePartial('SimpleBrowser', 'MockFetchSimpleBrowser', array('fetchResponse'));
    
    class TestOfFetchingMethods extends UnitTestCase {
        function TestOfFetchingMethods() {
            $this->UnitTestCase();
        }
        function testGet() {
            $response = &new MockSimpleHttpResponse($this);
            $response->setReturnValue("getContent", "stuff");
            $url = new SimpleUrl("http://this.com/page.html");
            $url->addRequestParameters(array("a" => "A", "b" => "B"));
            $browser = &new MockFetchSimpleBrowser($this);
            $browser->setReturnReference("fetchResponse", $response);
            $browser->expectArguments(
                    "fetchResponse",
                    array($url, "*"));
            $browser->SimpleBrowser();
            $this->assertIdentical(
                    $browser->get("http://this.com/page.html", array("a" => "A", "b" => "B"), &$this->_request),
                    "stuff");
            $browser->tally();
        }
        function testHead() {
            $response = &new MockSimpleHttpResponse($this);
            $response->setReturnValue("getContent", "stuff");
            $url = new SimpleUrl("http://this.com/page.html");
            $url->addRequestParameters(array("a" => "A", "b" => "B"));
            $browser = &new MockFetchSimpleBrowser($this);
            $browser->setReturnReference("fetchResponse", $response);
            $browser->expectArguments(
                    "fetchResponse",
                    array($url, "*"));
            $browser->expectCallCount("fetchResponse", 1);
            $browser->SimpleBrowser();
            $this->assertIdentical(
                    $browser->head("http://this.com/page.html", array("a" => "A", "b" => "B"), &$this->_request),
                    true);
            $browser->tally();
        }
        function testPost() {
            $response = &new MockSimpleHttpResponse($this);
            $response->setReturnValue("getContent", "stuff");
            $expected_request = new SimpleHttpPushRequest(new SimpleUrl("http://this.com/page.html"), "a=A&b=B");
            $expected_request->addHeaderLine('Content-Type: application/x-www-form-urlencoded');
            $browser = &new MockFetchSimpleBrowser($this);
            $browser->setReturnReference("fetchResponse", $response);
            $browser->expectCallCount("fetchResponse", 1);
            $browser->expectArguments(
                    "fetchResponse",
                    array(new SimpleUrl("http://this.com/page.html"), $expected_request));
            $browser->SimpleBrowser();
            $this->assertIdentical(
                    $browser->post("http://this.com/page.html", array("a" => "A", "b" => "B"), &$this->_request),
                    "stuff");
            $browser->tally();
        }
    }

    class TestOfBrowserAssertions extends UnitTestCase {
        function TestOfBrowserAssertions() {
            $this->UnitTestCase();
        }
        function testAssertionChaining() {
            $test = &new MockUnitTestCase($this);
            $test->expectArgumentsAt(0, "assertTrue", array(true, "Good"));
            $test->expectArgumentsAt(1, "assertTrue", array(false, "Bad"));
            $test->expectCallCount("assertTrue", 2);
            $browser = &new TestBrowser($test);
            $browser->_assertTrue(true, "Good");
            $browser->_assertTrue(false, "Bad");
            $test->tally();
        }
    }

    class TestOfBadHosts extends UnitTestCase {
        function TestOfBadHosts() {
            $this->UnitTestCase();
        }
        function &_createSimulatedBadHost() {
            $response = &new MockSimpleHttpResponse($this);
            $response->setReturnValue("isError", true);
            $response->setReturnValue("getError", "Bad socket");
            $response->setReturnValue("getNewCookies", array());
            $response->setReturnValue("getContent", false);
            $request = &new MockSimpleHttpRequest($this);
            $request->setReturnReference("fetch", $response);
            return $request;
        }
        function testUntestedHost() {
            $test = &new MockUnitTestCase($this);
            $test->expectCallCount("assertTrue", 0);
            $browser = &new TestBrowser($test);
            $request = &$this->_createSimulatedBadHost();
            $this->assertIdentical(
                    $browser->get("http://this.host/this/path/page.html", false, &$request),
                    false);
            $test->tally();
        }
        function testFailingBadHost() {
            $test = &new MockUnitTestCase($this);
            $browser = &new TestBrowser($test);
            $request = &$this->_createSimulatedBadHost();
            $this->assertIdentical(
                    $browser->get("http://this.host/this/path/page.html", false, &$request),
                    false);
        }
        function testExpectingBadHost() {
            $test = &new MockUnitTestCase($this);
            $browser = &new TestBrowser($test);
            $request = &$this->_createSimulatedBadHost();
            $this->assertIdentical(
                    $browser->get("http://this.host/this/path/page.html", false, &$request),
                    false);
        }
    }

    class TestOfHeaderExpectations extends UnitTestCase {
        function TestOfHeaderExpectations() {
            $this->UnitTestCase();
        }
        function setUp() {
            $this->_response = &new MockSimpleHttpResponse($this);
            $this->_response->setReturnValue("isError", false);
            $this->_response->setReturnValue("getNewCookies", array());
            $this->_response->setReturnValue("getContent", false);
            $this->_request = &new MockSimpleHttpRequest($this);
            $this->_request->setReturnReference("fetch", $this->_response);
            $this->_test = &new MockUnitTestCase($this);
        }
        function testResponseCode() {
            $this->_response->setReturnValue("getResponseCode", 404);
            $this->_test->expectArguments("assertTrue", array(true, "*"));
            $this->_test->expectCallCount("assertTrue", 1);
            $browser = &new TestBrowser($this->_test);
            $browser->get("http://this.host/this/path/page.html", false, &$this->_request);
            $browser->assertResponse(array(404));
            $this->_test->tally();
        }
        function testBadResponse() {
            $this->_response->setReturnValue("getResponseCode", false);
            $this->_response->setReturnValue("isError", true);
            $this->_test->expectArguments("assertTrue", array(false, "*"));
            $this->_test->expectCallCount("assertTrue", 1);
            $browser = &new TestBrowser($this->_test);
            $browser->get("http://this.host/this/path/page.html", false, &$this->_request);
            $browser->assertResponse(array(404));
            $this->_test->tally();
        }
        function testMimeTypes() {
            $this->_response->setReturnValue("getMimeType", "text/plain");
            $this->_test->expectArguments("assertTrue", array(true, "*"));
            $this->_test->expectCallCount("assertTrue", 1);
            $browser = &new TestBrowser($this->_test);
            $browser->get("http://this.host/this/path/page.html", false, &$this->_request);
            $browser->assertMime("text/plain");
            $this->_test->tally();
        }
    }
    
    class testOfExpectedCookies extends UnitTestCase {
        function TestOfExpectedCookies() {
            $this->UnitTestCase();
        }
        function &_createStandardResponse() {
            $response = &new MockSimpleHttpResponse($this);
            $response->setReturnValue("isError", false);
            $response->setReturnValue("getNewCookies", array());
            $response->setReturnValue("getContent", "stuff");
            return $response;
        }
        function &_createCookieSite($cookies) {
            $response = &new MockSimpleHttpResponse($this);
            $response->setReturnValue("isError", false);
            $response->setReturnValue("getContent", "stuff");
            $response->setReturnValue("getNewCookies", $cookies);
            $request = &new MockSimpleHttpRequest($this);
            $request->setReturnReference("fetch", $response);
            return $request;
        }
        function testMissingCookie() {
            $request = &new MockSimpleHttpRequest($this);
            $request->setReturnReference("fetch", $this->_createStandardResponse());
            $test = &new MockUnitTestCase($this);
            $test->expectArguments("assertTrue", array(false, "*"));
            $test->expectCallCount("assertTrue", 1);
            $browser = &new TestBrowser($test);
            $browser->expectCookie("a", "A");
            $browser->get("http://this.host/this/path/page.html", false, &$request);
            $test->tally();
            $this->assertIdentical($browser->getCookieValue("this.host", "this/page/", "a"), false);
        }
        function testNewCookie() {
            $request = &$this->_createCookieSite(array(new SimpleCookie("a", "A", "this/path/")));
            $test = &new MockUnitTestCase($this);
            $test->expectArguments("assertTrue", array(true, "*"));
            $test->expectCallCount("assertTrue", 1);
            $browser = &new TestBrowser($test);
            $browser->expectCookie("a", "A");
            $browser->get("http://this-host.com/this/path/page.html", false, &$request);
            $test->tally();
            $this->assertEqual($browser->getCookieValue("this-host.com", "this/path/", "a"), "A");
            $this->assertIdentical($browser->getCookieValue("this-host.com", "this/", "a"), false);
            $this->assertIdentical($browser->getCookieValue("another.com", "this/path/", "a"), false);
        }
    }
?>