<?php
    require_once(dirname(__FILE__) . '/../exceptions.php');
    require_once(dirname(__FILE__) . '/../expectation.php');
    require_once(dirname(__FILE__) . '/../test_case.php');
    Mock::generate('SimpleTestCase');
    Mock::generate('SimpleExpectation');

    class MyTestException extends Exception {}
    class HigherTestException extends MyTestException {}
    class OtherTestException extends Exception {}

    class TestOfExceptionExpectation extends UnitTestCase {

        function testExceptionClassAsStringWillMatchExceptionsRootedOnThatClass() {
            $expectation = new ExceptionExpectation('MyTestException');
            $this->assertTrue($expectation->test(new MyTestException()));
            $this->assertTrue($expectation->test(new HigherTestException()));
            $this->assertFalse($expectation->test(new OtherTestException()));
        }

        function testMatchesClassAndMessageWhenExceptionExpected() {
            $expectation = new ExceptionExpectation(new MyTestException('Hello'));
            $this->assertTrue($expectation->test(new MyTestException('Hello')));
            $this->assertFalse($expectation->test(new HigherTestException('Hello')));
            $this->assertFalse($expectation->test(new OtherTestException('Hello')));
            $this->assertFalse($expectation->test(new MyTestException('Goodbye')));
            $this->assertFalse($expectation->test(new MyTestException()));
        }

        function testMessagelessExceptionMatchesOnlyOnClass() {
            $expectation = new ExceptionExpectation(new MyTestException());
            $this->assertTrue($expectation->test(new MyTestException()));
            $this->assertFalse($expectation->test(new HigherTestException()));
        }
    }

    class TestOfExceptionQueue extends UnitTestCase {

        function testNoExceptionsInQueueMeansNoTestMessages() {
            $test = new MockSimpleTestCase();
            $test->expectNever('assert');
            $queue = new SimpleExpectedExceptionQueue();
            $this->assertFalse($queue->isExpected($test, new Exception()));
        }

        function testMatchingExceptionGivesTrue() {
            $expectation = new MockSimpleExpectation();
            $expectation->setReturnValue('test', true);
            $test = new MockSimpleTestCase();
            $test->setReturnValue('assert', true);
            $queue = new SimpleExpectedExceptionQueue();
            $queue->expectException($expectation, 'message');
            $this->assertTrue($queue->isExpected($test, new Exception()));
        }

        function testMatchingExceptionTriggersAssertion() {
            $test = new MockSimpleTestCase();
            $test->expectOnce('assert', array(
                    '*',
                    new ExceptionExpectation(new Exception()),
                    'message'));
            $queue = new SimpleExpectedExceptionQueue();
            $queue->expectException(new ExceptionExpectation(new Exception()), 'message');
            $queue->isExpected($test, new Exception());
        }
    }
?>