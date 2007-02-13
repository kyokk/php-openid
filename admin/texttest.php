<?php

define('Auth_OpenID_SHA256_SUPPORTED', false);
define('Auth_OpenID_HMACSHA256_SUPPORTED', false);

require_once 'Tests/TestDriver.php';
require_once 'PHPUnit/TestResult.php';
require_once 'Console/Getopt.php';

class TextTestResult extends PHPUnit_TestResult {
    function addError(&$test, &$t)
    {
        parent::addError($test, $t);
        echo "E";
    }

    function addFailure(&$test, &$t)
    {
        parent::addFailure($test, $t);
        echo "F";
    }

    function addPassedTest(&$test)
    {
        parent::addPassedTest($test);
        echo ".";
    }

    function dumpBadResults()
    {
        foreach ($this->failures() as $failure) {
            echo $failure->toString();
        }

        foreach ($this->errors() as $failure) {
            echo $failure->toString();
        }
    }
}

function microtime_float()
{
   list($usec, $sec) = explode(" ", microtime());
   return ((float)$usec + (float)$sec);
}

$longopts = array('no-math',
                  'math-lib=',
                  'insecure-rand',
                  'thorough');

$con  = new Console_Getopt;
$args = $con->readPHPArgv();
array_shift($args);
$options = $con->getopt2($args, "", $longopts);

if (PEAR::isError($options)) {
    print $options->message . "\n";
    exit(1);
}

list($flags, $tests_to_run) = $options;

$math_type = array();
$thorough = false;
foreach ($flags as $flag) {
    list($option, $value) = $flag;
    switch ($option) {
    case '--insecure-rand':
        define('Auth_OpenID_RAND_SOURCE', null);
        break;
    case '--no-math':
        define('Auth_OpenID_NO_MATH_SUPPORT', true);
        break;
    case '--math-lib':
        $math_type[] = $value;
        break;
    case '--thorough':
        define('Tests_Auth_OpenID_thorough', true);
        break;
    default:
        print "Unrecognized option: $option\n";
        exit(1);
    }
}

// ******** Math library selection ***********

if ($math_type) {
    if (defined('Auth_OpenID_NO_MATH_SUPPORT')) {
        print "--no-math and --math-lib are mutually exclusive\n";
        exit(1);
    }
    require_once('Auth/OpenID/BigMath.php');
    $new_extensions = array();
    foreach ($math_type as $lib) {
        $found = false;
        foreach ($_Auth_OpenID_math_extensions as $ext) {
            if ($ext['extension'] == $lib) {
                $new_extensions[] = $ext;
                $found = true;
                break;
            }
        }

        if (!$found) {
            print "Unknown math library specified: $lib\n";
            exit(1);
        }
    }
    $_Auth_OpenID_math_extensions = $new_extensions;
}

// ******** End math library selection **********

$suites = loadSuite($argv);

$totals = array(
    'run' => 0,
    'error' => 0,
    'failure' => 0,
    'time' => 0
    );

foreach ($suites as $suite) {
    $name = $suite->getName();
    echo "==========================================
Test suite: $name
------------------------------------------
";

    $result = new TextTestResult();
    $before = microtime_float();
    $suite->run($result);
    $after = microtime_float();

    $run = $result->runCount();
    $error = $result->errorCount();
    $failure = $result->failureCount();
    $delta = $after - $before;
    $totals['run'] += $run;
    $totals['error'] += $error;
    $totals['failure'] += $failure;
    $totals['time'] += $delta;
    $human_delta = round($delta, 3);
    echo "\nRan $run tests in $human_delta seconds";
    if ($error || $failure) {
        echo " with $error errors, $failure failures";
    }
    echo "
==========================================

";

    $failures = $result->failures();
    foreach($failures as $failure) {
        $test = $failure->failedTest();
        $testName = $test->getName();
        $exception = $failure->thrownException();
        echo "* Failure in $testName: $exception

";
    }
}

$before = microtime_float();
$run = $totals['run'];
$error = $totals['error'];
$failure = $totals['failure'];
$time = round($totals['time'], 3);
echo "Ran a total of $run tests in $time seconds with $error errors, $failure failures\n";
if ($totals['error'] || $totals['failure']) {
    exit(1);
}

?>
