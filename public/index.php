<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\ErrorHandler;
use App\Core\Http\Kernel;
use App\Core\Http\Request;

// Under `php -S ... public/index.php`, let the built-in server deliver real files in public/.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if ($file !== __FILE__ && is_file($file)) {
        return false;
    }
}

$container = require dirname(__DIR__) . '/config/bootstrap.php';
ErrorHandler::registerPhpHandlers();

$request = Request::fromGlobals($container->get(Config::class)->get('http.trusted_proxies'));
$container->get(Kernel::class)->handle($request)->send();
