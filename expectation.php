<?php
    // $Id$
    
    if (!defined("SIMPLE_TEST")) {
        define("SIMPLE_TEST", "simpletest/");
    }
    require_once(SIMPLE_TEST . 'dumper.php');
    
    /**
     *    Assertion that can display failure information.
     *    Also includes various helper methods.
     *    @abstract
     */
    class SimpleExpectation {
        var $_dumper;
        
        /**
         *    Creates a dumper for displaying values.
         */
        function SimpleExpectation() {
            $this->_dumper = &new SimpleDumper();
        }
        
        /**
         *    Tests the expectation. True if correct.
         *    @param mixed $compare        Comparison value.
         *    @return boolean              True if correct.
         *    @access public
         *    @abstract
         */
        function test($compare) {
        }
        
        /**
         *    Returns a human readable test message.
         *    @param mixed $compare      Comparison value.
         *    @return string             Description of success
         *                               or failure.
         *    @access public
         *    @abstract
         */
        function testMessage($compare) {
        }
        
        /**
         *    Accessor for the dumper.
         *    @return SimpleDumper    Current value dumper.
         *    @access protected
         */
        function &_getDumper() {
            return $this->_dumper;
        }
    }
    
    /**
     *    Test for equality.
     */
    class EqualExpectation extends SimpleExpectation {
        var $_value;
        
        /**
         *    Sets the value to compare against.
         *    @param mixed $value        Test value to match.
         *    @access public
         */
        function EqualExpectation($value) {
            $this->SimpleExpectation();
            $this->_value = $value;
        }
        
        /**
         *    Tests the expectation. True if it matches the
         *    held value.
         *    @param mixed $compare        Comparison value.
         *    @return boolean              True if correct.
         *    @access public
         */
        function test($compare) {
            return (($this->_value == $compare) && ($compare == $this->_value));
        }
        
        /**
         *    Returns a human readable test message.
         *    @param mixed $compare      Comparison value.
         *    @return string             Description of success
         *                               or failure.
         *    @access public
         */
        function testMessage($compare) {
            if ($this->test($compare)) {
                return "Equal expectation [" . $this->_dumper->describeValue($this->_value) . "]";
            } else {
                return "Equal expectation fails " .
                        $this->_dumper->describeDifference($this->_value, $compare);
            }
        }
        
        /**
         *    Accessor for comparison value.
         *    @return mixed       Held value to compare with.
         *    @access protected
         */
        function _getValue() {
            return $this->_value;
        }
    }
    
    /**
     *    Test for inequality.
     */
    class NotEqualExpectation extends EqualExpectation {
        
        /**
         *    Sets the value to compare against.
         *    @param mixed $value        Test value to match.
         *    @access public
         */
        function NotEqualExpectation($value) {
            $this->EqualExpectation($value);
        }
        
        /**
         *    Tests the expectation. True if it differs from the
         *    held value.
         *    @param mixed $compare        Comparison value.
         *    @return boolean              True if correct.
         *    @access public
         */
        function test($compare) {
            return ! parent::test($compare);
        }
        
        /**
         *    Returns a human readable test message.
         *    @param mixed $compare      Comparison value.
         *    @return string             Description of success
         *                               or failure.
         *    @access public
         */
        function testMessage($compare) {
            $dumper = &$this->_getDumper();
            if ($this->test($compare)) {
                return "Not equal expectation passes " .
                        $dumper->describeDifference($this->_getValue(), $compare);
            } else {
                return "Not equal expectation fails [" .
                        $dumper->describeValue($this->_getValue()) .
                        "] matches";
            }
        }
    }
    
    /**
     *    Test for identity.
     */
    class IdenticalExpectation extends EqualExpectation {
        
        /**
         *    Sets the value to compare against.
         *    @param mixed $value        Test value to match.
         *    @access public
         */
        function IdenticalExpectation($value) {
            $this->EqualExpectation($value);
        }
        
        /**
         *    Tests the expectation. True if it exactly
         *    matches the held value.
         *    @param mixed $compare        Comparison value.
         *    @return boolean              True if correct.
         *    @access public
         */
        function test($compare) {
            return ($this->_getValue() === $compare);
        }
        
        /**
         *    Returns a human readable test message.
         *    @param mixed $compare      Comparison value.
         *    @return string             Description of success
         *                               or failure.
         *    @access public
         */
        function testMessage($compare) {
            $dumper = &$this->_getDumper();
            if ($this->test($compare)) {
                return "Identical expectation [" . $dumper->describeValue($this->_getValue()) . "]";
            } else {
                return "Identical expectation [" . $dumper->describeValue($this->_getValue()) .
                        "] fails with [" .
                        $this->_dumper->describeValue($compare) . "] " .
                        $this->_dumper->describeDifference(
                                $this->_getValue(),
                                $compare,
                                TYPE_MATTERS);
            }
        }
    }
    
    /**
     *    Test for non-identity.
     */
    class NotIdenticalExpectation extends IdenticalExpectation {
        
        /**
         *    Sets the value to compare against.
         *    @param mixed $value        Test value to match.
         *    @access public
         */
        function NotIdenticalExpectation($value) {
            $this->IdenticalExpectation($value);
        }
        
        /**
         *    Tests the expectation. True if it differs from the
         *    held value.
         *    @param mixed $compare        Comparison value.
         *    @return boolean              True if correct.
         *    @access public
         */
        function test($compare) {
            return ! parent::test($compare);
        }
        
        /**
         *    Returns a human readable test message.
         *    @param mixed $compare      Comparison value.
         *    @return string             Description of success
         *                               or failure.
         *    @access public
         */
        function testMessage($compare) {
            $dumper = &$this->_getDumper();
            if ($this->test($compare)) {
                return "Not identical expectation passes " .
                        $dumper->describeDifference($this->_getValue(), $compare, TYPE_MATTERS);
            } else {
                return "Not identical expectation [" . $dumper->describeValue($this->_getValue()) . "] matches";
            }
        }
    }
    
    /**
     *    Test for a pattern using Perl regex rules.
     */
    class WantedPatternExpectation extends SimpleExpectation {
        var $_pattern;
        
        /**
         *    Sets the value to compare against.
         *    @param string $pattern        Pattern to search for.
         *    @access public
         */
        function WantedPatternExpectation($pattern) {
            $this->SimpleExpectation();
            $this->_pattern = $pattern;
        }
        
        /**
         *    Accessor for the pattern.
         *    @return string       Perl regex as string.
         *    @access protected
         */
        function _getPattern() {
            return $this->_pattern;
        }
        
        /**
         *    Tests the expectation. True if the Perl regex
         *    matches the comparison value.
         *    @param string $compare        Comparison value.
         *    @return boolean               True if correct.
         *    @access public
         */
        function test($compare) {
            return (boolean)preg_match($this->_getPattern(), $compare);
        }
        
        /**
         *    Returns a human readable test message.
         *    @param mixed $compare      Comparison value.
         *    @return string             Description of success
         *                               or failure.
         *    @access public
         */
        function testMessage($compare) {
            if ($this->test($compare)) {
                return $this->_decribePatternMatch($this->_getPattern(), $compare);
            } else {
                $dumper = &$this->_getDumper();
                return "Pattern [" . $this->_getPattern() .
                        "] not detected in [" .
                        $dumper->describeValue($compare) . "]";
            }
        }
        
        /**
         *    Describes a pattern match including the string
         *    found and it's position.
         *    @param string $pattern        Regex to match against.
         *    @param string $subject        Subject to search.
         *    @access protected
         */
        function _decribePatternMatch($pattern, $subject) {
            preg_match($pattern, $subject, $matches);
            $position = strpos($subject, $matches[0]);
            $dumper = &$this->_getDumper();
            return "Pattern [$pattern] detected at [$position] in [" .
                    $dumper->describeValue($subject) . "] as [" .
                    $matches[0] . "] in region [" .
                    $dumper->clipString($subject, 40, $position) . "]";
        }
    }
    
    /**
     *    Fail if a pattern is detected within the
     *    comparison.
     */
    class UnwantedPatternExpectation extends WantedPatternExpectation {
        
        /**
         *    Sets the reject pattern
         *    @param string $pattern        Pattern to search for.
         *    @access public
         */
        function UnwantedPatternExpectation($pattern) {
            $this->WantedPatternExpectation($pattern);
        }
        
        /**
         *    Tests the expectation. False if the Perl regex
         *    matches the comparison value.
         *    @param string $compare        Comparison value.
         *    @return boolean               True if correct.
         *    @access public
         */
        function test($compare) {
            return ! parent::test($compare);
        }
        
        /**
         *    Returns a human readable test message.
         *    @param string $compare      Comparison value.
         *    @return string              Description of success
         *                                or failure.
         *    @access public
         */
        function testMessage($compare) {
            if ($this->test($compare)) {
                $dumper = &$this->_getDumper();
                return "Pattern [" . $this->_getPattern() .
                        "] not detected in [" .
                        $dumper->describeValue($compare) . "]";
            } else {
                return $this->_decribePatternMatch($this->_getPattern(), $compare);
            }
        }
    }
?>