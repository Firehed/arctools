<?php

namespace Firehed\Arctools\Unit;

use Exception;
use ArcanistWorkingCopyIdentity;
use ArcanistNoEffectException;

/**
 * @coversDefaultClass Firehed\Arctools\Unit\PHPUnitTestEngine
 * @covers ::<protected>
 * @covers ::<private>
 */
class PHPUnitTestEngineTest extends \PHPUnit_Framework_TestCase {

    /**
     * @covers ::run
     * @expectedException ArcanistNoEffectException
     */
    public function testRunWithNoPaths() {
        $engine = new PHPUnitTestEngine;
        $wc = $this->getWC([]);
        $paths = [];
        $engine->setWorkingCopy($wc)
            ->setPaths($paths);
        $engine->run();
    } // testRunWithNoPaths

    /**
     * @covers ::run
     * @expectedException ArcanistNoEffectException
     */
    public function testOutOfScopePath() {
        $engine = new PHPUnitTestEngine;
        $wc = $this->getWC([]);
        $paths = ['outofscope/C.php'];
        $engine->setWorkingCopy($wc)
            ->setPaths($paths)
            ->run();
    } // testOutOfScopePath

    /**
     * @covers ::run
     */
    public function testStandardLocations() {
        $engine = new PHPUnitTestEngine;
        $wc = $this->getWC([]);
        $results = $engine->setWorkingCopy($wc)
            ->setRunAllTests(true)
            ->run();
        $this->assertContainsOnlyInstancesOf('ArcanistUnitTestResult',
            $results);
        $this->assertCount(1, $results);
        list($result) = $results;
        $this->assertSame('ATest::testNothing',
            $result->getName(),
            'Wrong test was run!');
    } // testStandardLocations

    /**
     * @covers ::run
     */
    public function testAlternativeLocations() {
        $engine = new PHPUnitTestEngine;
        $wc = $this->getWC([
            'phpunit.source_directory' => 'source',
            'phpunit.test_directory'  => 'thetests',
        ]);
        $results = $engine->setWorkingCopy($wc)
            ->setRunAllTests(true)
            ->run();
        $this->assertContainsOnlyInstancesOf('ArcanistUnitTestResult',
            $results);
        $this->assertCount(1, $results);
        list($result) = $results;
        $this->assertSame('BTest::testNothing',
            $result->getName(),
            'Wrong test was run!');
    } // testAlternativeLocations

    /**
     * @covers ::run
     * @dataProvider badConfigs
     */
    public function testBadConfigValues(array $config) {
        $engine = new PHPUnitTestEngine;
        $wc = $this->getWC($config);
        try {
            $engine->setWorkingCopy($wc)
                ->run();
        }
        catch (Exception $e) {
            if ($e instanceof ArcanistNoEffectException) {
                $this->fail("The wrong exception type was thrown");
            }
            return;
        }
        $this->fail("No exception was thrown");
    } // testBadConfigValues

    // ----(DataProviders)------------------------------------------------------

    public function badConfigs() {
        return [
            [['phpunit.source_directory' => 'fakepath']],
            [['phpunit.test_directory' => 'fakepath']],
            [['phpunit.config' => 'fakepath']],
        ];
    } // badConfigs

    private function getWC(array $config) {
        return ArcanistWorkingCopyIdentity::newFromRootAndConfigFile(
            __DIR__.'/fixtures/fakeproject',
            json_encode($config),
            null);
    } // getWC

}
