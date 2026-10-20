<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Container;
use Dotenv\Dotenv;

/*
 * Shared bootstrap for the web front controller, bin/console and the test suite.
 * Returns the configured container.
 */

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';

// Real environment variables (Laravel Cloud) always win; .env is a local convenience.
Dotenv::createImmutable($root)->safeLoad();

$config = new Config(require __DIR__ . '/app.php');

date_default_timezone_set($config->string('app.timezone', 'UTC'));
ini_set('display_errors', $config->bool('app.debug') ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', $config->string('log.path') . '/php-errors.log');
error_reporting(E_ALL);

$container = new Container();
$container->instance(Config::class, $config);
$container->instance(Container::class, $container);
(require __DIR__ . '/container.php')($container, $config);

return $container;
