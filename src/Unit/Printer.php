<?php

namespace Firehed\Arctools\Unit;

use Exception;
use PHPUnit\Framework\ {
    AssertionFailedError,
    Test,
    TestListener,
    TestSuite,
    Warning
};
use PHPUnit\Util\Printer as BasePrinter;

class Printer extends BasePrinter implements TestListener
{
    public function __construct($out = null)
    {
        parent::__construct($out);
        print_r($_SERVER);
    }
    /**
     * An error occurred.
     *
     * @param Test       $test
     * @param \Exception $e
     * @param float      $time
     */
    public function addError(Test $test, \Exception $e, $time)
    {
        echo 'E';
    }
    /**
     * A warning occurred.
     *
     * @param Test    $test
     * @param Warning $e
     * @param float   $time
     */
    public function addWarning(Test $test, Warning $e, $time)
    {
        echo 'W';
    }
    /**
     * A failure occurred.
     *
     * @param Test                 $test
     * @param AssertionFailedError $e
     * @param float                $time
     */
    public function addFailure(Test $test, AssertionFailedError $e, $time)
    {
        echo 'F';
    }
    /**
     * Incomplete test.
     *
     * @param Test       $test
     * @param \Exception $e
     * @param float      $time
     */
    public function addIncompleteTest(Test $test, \Exception $e, $time)
    {
        echo 'I';
    }
    /**
     * Risky test.
     *
     * @param Test       $test
     * @param \Exception $e
     * @param float      $time
     */
    public function addRiskyTest(Test $test, \Exception $e, $time)
    {
        echo 'R';
    }
    /**
     * Skipped test.
     *
     * @param Test       $test
     * @param \Exception $e
     * @param float      $time
     */
    public function addSkippedTest(Test $test, \Exception $e, $time)
    {
        echo 'S';
    }
    /**
     * A test suite started.
     *
     * @param TestSuite $suite
     */
    public function startTestSuite(TestSuite $suite)
    {
        echo 'start';
    }
    /**
     * A test suite ended.
     *
     * @param TestSuite $suite
     */
    public function endTestSuite(TestSuite $suite)
    {
        echo 'end';
    }
    /**
     * A test started.
     *
     * @param Test $test
     */
    public function startTest(Test $test)
    {
        echo ' bt ';
    }
    /**
     * A test ended.
     *
     * @param Test  $test
     * @param float $time
     */
    public function endTest(Test $test, $time)
    {
        echo ' et ';
    }
}
