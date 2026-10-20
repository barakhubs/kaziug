<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Http\HttpException;
use App\Core\Http\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testMatchesParametersAndGroupPrefixAndMiddleware(): void
    {
        $router = new Router();
        $router->group('/admin', ['AuthMw'], function (Router $r): void {
            $r->get('/jobs/{id:\d+}/edit', ['JobsController', 'edit'], ['ExtraMw']);
        });

        [$route, $params] = $router->match('GET', '/admin/jobs/42/edit');

        $this->assertSame(['id' => '42'], $params);
        $this->assertSame(['AuthMw', 'ExtraMw'], $route->middleware);
        $this->assertSame(['JobsController', 'edit'], $route->handler);
    }

    public function testParameterConstraintRejectsNonMatchingSegment(): void
    {
        $router = new Router();
        $router->get('/jobs/{id:\d+}', ['C', 'm']);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $router->match('GET', '/jobs/abc');
    }

    public function testWrongMethodGives405WithAllowHeader(): void
    {
        $router = new Router();
        $router->post('/subscribe', ['C', 'm']);

        try {
            $router->match('GET', '/subscribe');
            $this->fail('Expected 405');
        } catch (HttpException $e) {
            $this->assertSame(405, $e->status);
            $this->assertSame('POST', $e->headers['Allow']);
        }
    }

    public function testHeadIsServedByGetRoute(): void
    {
        $router = new Router();
        $router->get('/', ['C', 'm']);

        [$route] = $router->match('HEAD', '/');
        $this->assertSame('GET', $route->method);
    }
}
