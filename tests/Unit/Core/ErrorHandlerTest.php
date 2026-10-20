<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\ErrorHandler;
use App\Core\Http\HttpException;
use App\Core\Http\Request;
use App\Core\View;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tests\Support\Fakes\NullLogger;

final class ErrorHandlerTest extends TestCase
{
    private function render(bool $debug, \Throwable $e, NullLogger $logger): \App\Core\Http\Response
    {
        $view = new View(dirname(__DIR__, 3) . '/views');
        $view->share('site', \App\Domain\SiteSettings::fromArray([], null));

        return (new ErrorHandler($logger, $view, $debug))->render($e, new Request('GET', '/x'));
    }

    public function testProductionShowsNoDetailsButLogsThem(): void
    {
        $logger = new NullLogger();
        $response = $this->render(false, new RuntimeException('SQLSTATE secret detail at /var/app/src/Foo.php'), $logger);

        $this->assertSame(500, $response->status());
        $this->assertStringNotContainsString('SQLSTATE', $response->body());
        $this->assertStringNotContainsString('Foo.php', $response->body());
        $this->assertStringNotContainsString('debug-trace', $response->body());
        $this->assertSame([['error', 'SQLSTATE secret detail at /var/app/src/Foo.php']], $logger->entries);
    }

    public function testDebugModeShowsTrace(): void
    {
        $response = $this->render(true, new RuntimeException('boom'), new NullLogger());

        $this->assertStringContainsString('debug-trace', $response->body());
        $this->assertStringContainsString('boom', $response->body());
    }

    public function testHttpExceptionsAreNotLoggedAsErrors(): void
    {
        $logger = new NullLogger();
        $response = $this->render(false, HttpException::notFound(), $logger);

        $this->assertSame(404, $response->status());
        $this->assertSame([], $logger->entries);
    }

    public function testDebugIsForcedOffInProduction(): void
    {
        $_ENV['APP_ENV'] = 'production';
        $_ENV['APP_DEBUG'] = 'true';
        try {
            $config = require dirname(__DIR__, 3) . '/config/app.php';
        } finally {
            $_ENV['APP_ENV'] = 'testing';
            $_ENV['APP_DEBUG'] = 'false';
        }

        $this->assertFalse($config['app']['debug']);
        $this->assertTrue($config['session']['secure']);
    }
}
