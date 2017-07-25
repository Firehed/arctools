<?php

namespace Firehed\Arctools\Unit;

use ArcanistTestResultParser;
use ArcanistUnitTestResult;
use SimpleXMLElement;
use UnexpectedValueException;

class ResultParser extends ArcanistTestResultParser
{

    public function parseTestResults($path, $testResults)
    {
        var_dump(func_get_args());
        if (!$testResults) {
            $result = id(new ArcanistUnitTestResult())
                ->setName($path)
                ->setUserData($this->stderr)
                ->setResult(ArcanistUnitTestResult::RESULT_BROKEN);
            return [$result];
        }

        $xml = new SimpleXmlElement($testResults);
        return array_map(
            [$this, 'parseTestCase'],
            $xml->xpath('//testcase')
        );
    }

    private function parseTestCase(SimpleXMLElement $testCase): ArcanistUnitTestResult
    {

        $name = (string) $testCase['class'] . '::' . (string) $testCase['name'];
        $duration = (float) $testCase['time'];
        $userData = '';

        $status = ArcanistUnitTestResult::RESULT_UNSOUND;

        if ((bool) $testCase->skipped) {
            $status = ArcanistUnitTestResult::RESULT_SKIP;
        } elseif ((bool) $testCase->error) {
            $error = $testCase->error;
            $type = (string) $error['type'];
            switch ($type) {
            }
            $status = ArcanistUnitTestResult::RESULT_BROKEN;
            $userData = $this->getUserData($error);
        } elseif ((bool) $testCase->failure) {
            $status = ArcanistUnitTestResult::RESULT_FAIL;
            $userData = $this->getUserData($testCase->failure);
        } else {
            if ((string) $testCase !== "") {
                throw new UnexpectedValueException('Passing test with body?');
            }
            $status = ArcanistUnitTestResult::RESULT_PASS;
        }

        $result = new ArcanistUnitTestResult();
        $result->setName($name);
        $result->setResult($status);
        $result->setDuration($duration);
        //        $result->setCoverage($coverage);
        $result->setUserData($userData);

        return $result;
    }

    private function getUserData(SimpleXMLElement $body): string
    {
        $string = (string) $body;

        return preg_replace('/^.+\n/', '', $string);
    }
}
