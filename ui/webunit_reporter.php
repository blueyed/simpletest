<?php
/**
 *	base include file for SimpleTest PUnit reporter
 *	@package	SimpleTest
 *	@subpackage	UnitTester
 *	@version	$Id$
 */

/**
 * @ignore    originally defined in simple_test.php
 */
if (!defined("SIMPLE_TEST")) {
	define("SIMPLE_TEST", "simpletest/");
}
require_once(SIMPLE_TEST . 'runner.php');
require_once(SIMPLE_TEST . 'reporter.php');
/**
 * Main sprintf template for the start of the page.
 * Sequence of parameters is:
 * - title - string
 * - script path - string
 * - script path - string
 * - css path - string
 * - additional css - string
 * - title - string
 * - image path - string
 */
define('SIMPLETEST_WEBUNIT_HEAD', <<<EOS
<html>
<head>
<title>%s</title>
<script type="text/javascript" src="%sx.js"></script>
<script type="text/javascript" src="%swebunit.js"></script>
<link rel="stylesheet" type="text/css" href="%swebunit.css" title="Default"></link>
<style type="text/css">
%s
</style>
</head>
<body>
<div id="wait">
	<h1>&nbsp;Running %s&nbsp;</h1>
	Please wait...<br />
	<img src="%swait.gif" border="0"><br />&nbsp;
</div>
<script type="text/javascript">
wait_start();
</script>
<!-- open a new script to capture js vars as the tests run -->
<script type="text/javascript">

EOS
);

/**
 *	Not used yet.
 *  May be needed for localized styles we need at runtime, not in the stylesheet.
 */
define('SIMPLETEST_WEBUNIT_CSS', '/* this space reseved for future use */');

    /**
     *    Sample minimal test displayer. Generates only
     *    failure messages and a pass count.
	 *	  @package SimpleTest
	 *	  @subpackage UnitTester
     */
    class WebUnitReporter extends SimpleReporter {
    	/**
    	 *    @var string Base directory for PUnit script, images and style sheets.
    	 *    Needs to be a relative path from where the test scripts are run 
    	 *    (and obviously, visible in the document root).
    	 */
    	var $path;
        
        /**
         *    Does nothing yet. The first output will
         *    be sent on the first test start. For use
         *    by a web browser.
         *    @access public
         */
        function WebUnitReporter($path='../ui/') {
            $this->SimpleReporter();
            $this->path = $path;
        }
        
        /**
         *    Paints the top of the web page setting the
         *    title to the name of the starting test.
         *    @param string $test_name      Name class of test.
         *    @access public
         */
        function paintHeader($test_name) {
            $this->sendNoCacheHeaders();
            echo sprintf(
            	SIMPLETEST_WEBUNIT_HEAD
            	,$test_name
            	,$this->path.'js/'
            	,$this->path.'js/'
            	,$this->path.'css/'
            	,$this->_getCss()
            	,$test_name
            	,$this->path.'img/'
            	);
            flush();
        }
        
        /**
         *    Send the headers necessary to ensure the page is
         *    reloaded on every request. Otherwise you could be
         *    scratching your head over out of date test data.
         *    @access public
         *    @static
         */
        function sendNoCacheHeaders() {
            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
            header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
            header("Cache-Control: no-store, no-cache, must-revalidate");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
        }
        
        /**
         *    Paints the CSS. Add additional styles here.
         *    @return string            CSS code as text.
         *    @access protected
         */
        function _getCss() {
            return SIMPLETEST_WEBUNIT_CSS;
        }
        
        /**
         *    Paints the end of the test with a summary of
         *    the passes and failures.
         *    @param string $test_name        Name class of test.
         *    @access public
         */
        function paintFooter($test_name) {
            echo '</script><script type="text/javascript">xHide(\'wait\');</script>';
            echo "<h1>$test_name</h1>\n";
            $colour = ($this->getFailCount() + $this->getExceptionCount() > 0 ? "red" : "green");
            print "<div style=\"";
            print "padding: 8px; margin-top: 1em; background-color: $colour; color: white;";
            print "\">";
            print $this->getTestCaseProgress() . "/" . $this->getTestCaseCount();
            print " test cases complete:\n";
            print "<strong>" . $this->getPassCount() . "</strong> passes, ";
            print "<strong>" . $this->getFailCount() . "</strong> fails and ";
            print "<strong>" . $this->getExceptionCount() . "</strong> exceptions.";
            print "</div>\n";
            print "</body>\n</html>\n";
        }
        
        /**
         *    Paints the test failure with a breadcrumbs
         *    trail of the nesting test suites below the
         *    top level test.
         *    @param string $message    Failure message displayed in
         *                              the context of the other tests.
         *    @access public
         */
        function paintFail($message) {
            parent::paintFail($message);
            print "<span class=\"fail\">Fail</span>: ";
            $breadcrumb = $this->getTestList();
            array_shift($breadcrumb);
            print implode("-&gt;", $breadcrumb);
            print "-&gt;" . htmlentities($message) . "<br />\n";
        }
        
        /**
         *    Paints a PHP error or exception.
         *    @param string $message        Message is ignored.
         *    @access public
         *    @abstract
         */
        function paintException($message) {
            parent::paintException($message);
            print "<span class=\"fail\">Exception</span>: ";
            $breadcrumb = $this->getTestList();
            array_shift($breadcrumb);
            print implode("-&gt;", $breadcrumb);
            print "-&gt;<strong>" . htmlentities($message) . "</strong><br />\n";
        }
        
        /**
         *    Paints formatted text such as dumped variables.
         *    @param string $message        Text to show.
         *    @access public
         */
        function paintFormattedMessage($message) {
            print "<pre>$message</pre>";
        }
    }
    
?>