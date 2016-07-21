<?php

/**
 * This is a web server based version.
 */

include(__DIR__ . '/vendor/autoload.php');

$o = new \FieldTop\OverflowChecker();

include('fieldtop_config.php');

if (!isset($userPass)) {
    throw new \Exception(getcwd() . '/fieldtop_config.php does not contain a valid configuration.');
}

$o->connectDB($userPass['user'], $userPass['pass']);
$o->check();

if (php_sapi_name() === 'cli') {
    $output = new Symfony\Component\Console\Output\ConsoleOutput();
    $o->showCLI($output);
} else {
    $o->showHTML();
}
