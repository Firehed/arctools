<?php

namespace Firehed\Arctools;

use ArcanistNoEffectException;
use ArcanistUnitTestEngine;
use Exception;
use ExecFuture;
use Filesystem;
use PhpunitResultParser;
use PhutilConsole;
use TempFile;

/**
 * PHPUnit wrapper
 *
 * To use, set unit.engine in .arcconfig, or use --engine flag
 * with arc unit. Currently supports only class & test files
 * (no directory support).
 *
 * A custom PHPUnit XML config file may be specified with phpunit.config in
 * .arcconfig.
 *
 * If your source code or tests live outside of src/ or tests/, respectively,
 * configure their location with `phpunit.source_directory` and
 * `phpunit.test_directory`. All test files must currently follow the
 * ClassTest.php naming convention which is already standard in PHPUnit.
 */
final class PHPUnitTestEngine extends ArcanistUnitTestEngine {

    private $console;
    private $phpunit_config_file;
    private $project_root;
    private $source_directory = 'src/';
    private $test_cases = [];
    private $test_directory = 'tests/';
    private $test_suffix = 'Test.php';

    /**
     * Execute the unit tests
     *
     * @return array Unit test results
     */
    public function run() {
        $this->console = PhutilConsole::getConsole();

        $this->project_root = $this->getWorkingCopy()->getProjectRoot().'/';
        $this->prepareConfigFile();

        if ($this->getRunAllTests()) {
            $paths = [$this->test_directory, $this->source_directory];
        }
        else {
            $paths = $this->getPaths();
        }

        // Expand relative path to its absolute counterpart
        foreach ($paths as &$path) {
            $path = Filesystem::resolvePath($path, $this->project_root);
        }
        $this->console->writeLog("##Finding tests##\n");
        $this->test_cases = $this->getTestsForPaths($paths);

        if (!$this->test_cases) {
            throw new ArcanistNoEffectException('No tests to run.');
        }

        $futures = array();
        $temp_files = array();

        // This is a really hacky but safe way to determine where libphutil
        // came from: simply search the list of included files for the init
        // script. This should allow `arc` to live absolutely anywhere without
        // making any assumptions about how you entered the workflow or whether
        // arcanist and libphutil are even used by the codebase.
        $search = 'libphutil'.DIRECTORY_SEPARATOR.'scripts'.
            DIRECTORY_SEPARATOR.'__init_script__.php';
        $include_path = null;
        foreach (get_included_files() as $include) {
            if ($search === substr($include, -strlen($search))) {
                $include_path = csprintf('--include-path %s',
                    substr($include, 0, -strlen($search)));
                break;
            }
        }

        foreach ($this->test_cases as $test_path) {
            $json_tmp = new TempFile();
            $clover_tmp = null;
            $clover = null;
            if ($this->getEnableCoverage() !== false) {
                $clover_tmp = new TempFile();
                $clover = csprintf('--coverage-clover %s', $clover_tmp);
            }

            $config = $this->phpunit_config_file
                ? csprintf('--configuration %s', $this->phpunit_config_file)
                : null;
            $bin = 'vendor/bin/phpunit';
            $phpunit = Filesystem::resolvePath($bin, $this->project_root);
            $stderr = '-d display_errors=stderr';
            $futures[$test_path] = new ExecFuture(
                '%C %C %C %C --log-json %s %C %s',
                $phpunit, $stderr, $config, $include_path,
                $json_tmp, $clover, $test_path);
            $temp_files[$test_path] = [
                'json' => $json_tmp,
                'clover' => $clover_tmp,
            ];
        } unset($test_path);

        $this->console->writeLog("##Executing tests##\n");
        $results = [];
        foreach (Futures($futures)->limit(4) as $test => $future) {
            list($err, $stdout, $stderr) = $future->resolve();
            $results[] = $this->parseTestResults($test,
                $temp_files[$test]['json'],
                $temp_files[$test]['clover'],
                $stderr);
        }

        return array_mergev($results);
    } // run

    /**
     * Get an array of appropriate test cases based on the changed or specified
     * files. This is based on common Composer layouts, so only changed source
     * code or test cases will be covered; changes to the vendor directory (if
     * it is not excluded from version control) will be ignored
     *
     * @return array<string, string> File being tested => Test Case
     */
    private function getTestsForPaths(array $paths) {
        $affected_tests = array();
        foreach ($paths as $path) {
            if (!file_exists($path)) {
                $this->console->writeLog(
                    "Skipping search for '%s': path invalid\n",
                    $path);
                continue;
            }

            if (is_dir($path)) {
                $path .= '/';
                if (!$this->isInSourceDirectory($path) &&
                    !$this->isInTestDirectory($path) &&
                    $path !== $this->project_root) {
                    $this->console->writeLog(
                        "Skipping out-of-scope directory '%s's\n",
                        $path);
                    continue;
                }
                $contents = Filesystem::listDirectory($path);
                foreach ($contents as &$content) {
                    $content = $path.$content;
                }

                $affected_tests += $this->getTestsForPaths($contents);
            }
            elseif ($test = $this->findTestFile($path)) {
                // The keys are used in coverage reports (and making the list
                // unique) and must correspond to the file being tested, not
                // the test case itself. Of course, when the test case has been
                // changed and it is under test, the two values will be the
                // same and no coverage report will be generated. Based on the
                // naming conventions we could probably do this in reverse, but
                // it's unnecessarily complicated for now.
                $affected_tests[$path] = $test;
            }
        }
        return $affected_tests;
    } // getTestsForPaths

    /**
     * Parse test results from phpunit json report
     *
     * @param string $path Path to test
     * @param string $json_path Path to phpunit json report
     * @param string $clover_tmp Path to phpunit clover report
     * @param string $stderr Data written to stderr
     * @return array
     */
    private function parseTestResults($path, $json_tmp, $clover_tmp, $stderr) {
        $test_results = Filesystem::readFile($json_tmp);
        return id(new PhpunitResultParser())
            ->setEnableCoverage($this->getEnableCoverage())
            ->setProjectRoot($this->getWorkingCopy()->getProjectRoot())
            ->setCoverageFile($clover_tmp)
            ->setAffectedTests($this->test_cases)
            ->setStderr($stderr)
            ->parseTestResults($path, $test_results);
    } // parseTestResults


    /**
     * Search for a test case that corresponds to a given file, following
     * reasonably standard naming conventions. If the file __is__ a test case,
     * the file is returned.
     *
     * The convention is that for a source code file at src/A/B/C.php, the test
     * case will exists at tests/A/B/CTest.php
     *
     * src/ is configurable with phpunit.source_directory
     * tests/ is configurable with phpunit.test_directory
     * Test.php suffix is not currently configurable
     *
     * @param string PHP file to locate test cases for.
     * @return string Path to test case (empty if not found)
     */
    private function findTestFile($path) {
        $ext = idx(pathinfo($path), 'extension');
        // If the source file was not PHP, don't even bother
        if ("php" !== $ext) {
            $this->console->writeLog("Skipping search for non-PHP file '%s'\n",
                $path);
            return "";
        }

        if ($this->isTestFile($path)) {
            $this->console->writeLog(
                "Including changed test case '%s'\n",
                $path);
            return $path;
        }
        elseif ($this->isInTestDirectory($path)) {
            $this->console->writeLog(
                "Skipping file in test directory '%s', presumed fixture\n",
                $path);
            return "";
        }
        elseif (!$this->isInSourceDirectory($path)) {
            $this->console->writeLog(
                "Skipping out-of-scope source file '%s'\n",
                $path);
            return "";
        }

        $prefix_length = strlen($this->source_directory);
        $assumed_class = substr($path, $prefix_length, -strlen('.php'));
        $likely_test = $this->test_directory.$assumed_class.$this->test_suffix;

        if (file_exists($likely_test)) {
            $this->console->writeLog(
                "Found test for changed file '%s' at '%s'\n",
                $path,
                $likely_test);
            return $likely_test;
        }

        $this->console->writeLog(
            "No test case found for changed file '%s' (expected at '%s')\n",
            $path,
            $likely_test);
        return "";
    } // findTestFile

    /**
     * Read, validate, and apply values from the project's configuration file.
     * This allows overriding the default search paths for source code and test
     * cases which are used in changeset detection, and setting a location for
     * a PHPUnit XML config file.
     *
     * @return void
     * @throws Exception
     */
    private function prepareConfigFile() {
        $this->console->writeLog("##Applying configuration##\n");

        $project_root = $this->project_root;
        $wc = $this->getWorkingCopy();
        $configurable = [
            'phpunit.config' => 'phpunit_config_file',
            'phpunit.source_directory' => 'source_directory',
            'phpunit.test_directory' => 'test_directory',
        ];

        // Unfortunately, this uses variable property access all over the
        // place. It's a necessary evil to avoid repeated logic for each
        // configurable value (it may be possible do some weird by-reference
        // thing instead, but that's just a different kind of confusing evil).
        foreach ($configurable as $key => $property) {
            if ($config = $wc->getProjectConfig($key)) {
                $resolved = Filesystem::resolvePath($config, $project_root);
                if (Filesystem::pathExists($resolved)) {
                    $this->$property = $resolved;
                }
                else {
                    throw new Exception(sprintf(
                        "The configured value of '%s', '%s' is a path that ".
                        "does not exist. Please double-check the value set ".
                        "in .arcconfig. The default value is '%s'. Relative ".
                        "paths are evaluated relative to the project root.",
                        $key,
                        $config,
                        $this->$property));
                }

            }
            // No value is configred, just resolve the default
            else {
                if ($this->$property) {
                    $this->$property = Filesystem::resolvePath($this->$property,
                        $project_root);
                }
            }

            $this->console->writeLog("Using '%s' for config value __%s__\n",
                $this->$property ? : 'null',
                $key);
        }
    } // prepareConfigFile

    /**
     * Check whether the path provided appears to be a test case
     *
     * @param string path to file
     * @return boolean
     */
    private function isTestFile($path) {
        $name = basename($path);
        $suffix_length = strlen($this->test_suffix);
        if ($this->test_suffix !== substr($name, -$suffix_length)) {
            return false;
        }
        return $this->isInTestDirectory($path);
    } // isTestFile

    /**
     * Check whether the path provided is in the tests directory
     *
     * @param string path to file
     * @return boolean
     */
    private function isInTestDirectory($path) {
        return (0 === strpos($path, $this->test_directory));
    } // isInTestDirectory

    /**
     * Check whether the path provide is in the source directory
     *
     * @param string path to file
     * @return boolean
     */
    private function isInSourceDirectory($path) {
        return (0 === strpos($path, $this->source_directory));
    } // isInSourceDirectory


    // Indicate that the --everything is supported
    protected function supportsRunAllTests() {
        return true;
    } // supportsRunAllTests

}
