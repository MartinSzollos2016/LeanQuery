<?php

use LeanMapper\Connection;
use LeanMapper\DefaultEntityFactory;
use Tester\Environment;

if (@!include __DIR__ . '/../vendor/autoload.php') {
	echo 'Install Nette Tester using `composer update --dev`';
	exit(1);
}

// configure environment
Environment::setup();
class_alias('Tester\Assert', 'Assert');
date_default_timezone_set('Europe/Prague');

$_SERVER = array_intersect_key($_SERVER, array_flip(array('PHP_SELF', 'SCRIPT_NAME', 'SERVER_ADDR', 'SERVER_SOFTWARE', 'HTTP_HOST', 'DOCUMENT_ROOT', 'OS', 'argc', 'argv')));
$_SERVER['REQUEST_TIME'] = 1234567890;
$_ENV = $_GET = $_POST = array();

if (extension_loaded('xdebug')) {
	xdebug_disable();
	Tester\CodeCoverage\Collector::start(__DIR__ . '/coverage.dat');
}

if (!copy(__DIR__ . '/db/library-ref.sq3', __DIR__ . '/db/library.sq3')) {
	echo 'Failed to copy SQLite database';
	exit(1);
}

require_once(__DIR__ . '/model.php');

$connection = new Connection(array(
	'driver' => 'sqlite3',
	'database' => __DIR__ . '/db/library.sq3',
));
$connection->onEvent[] = function ($event) use (&$queries) {
	$queries[] = $event->sql;
};
$mapper = new Mapper;
$entityFactory = new DefaultEntityFactory;
