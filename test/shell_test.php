<?php
    // $Id$
    
    class TestOfShell extends UnitTestCase {
        function TestOfShell() {
            $this->UnitTestCase();
        }
        function testEcho() {
            $shell = &new SimpleShell();
            $this->assertIdentical($shell->execute('echo Hello'), 0);
            $this->assertWantedPattern('/Hello/', $shell->getOutput());
        }
        function testBadCommand() {
            $shell = &new SimpleShell();
            $this->assertNotEqual($ret = $shell->execute('blurgh!'), 0);
        }
    }
    
    class TestOfShellTesterAndShell extends ShellTestCase {
        function TestOfShellTesterAndShell() {
            $this->ShellTestCase();
        }
        function testEcho() {
            $this->assertTrue($this->execute('echo Hello'));
            $this->assertExitCode(0);
            $this->assertWantedPattern('/hello/i');
        }
    }
?>