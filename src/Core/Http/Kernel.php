<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Container;
use App\Core\ErrorHandler;
use Throwable;

/**
 * Turns a Request into a Response: global middleware, routing, route middleware, controller.
 * Errors are normally rendered by the RenderErrors middleware (inside the session and
 * security-header layers); the catch here is a last resort for failures in those layers.
 */
final class Kernel
{
    /** @param list<class-string> $globalMiddleware */
    public function __construct(
        private readonly Container $container,
        private readonly Router $router,
        private readonly MiddlewarePipeline $pipeline,
        private readonly ErrorHandler $errors,
        private readonly array $globalMiddleware,
    ) {
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->pipeline->handle($request, $this->globalMiddleware, $this->dispatch(...));
        } catch (Throwable $e) {
            return $this->errors->renderPlain($e);
        }
    }

    private function dispatch(Request $request): Response
    {
        [$route, $params] = $this->router->match($request->method, $request->path);
        foreach ($params as $key => $value) {
            $request->setAttribute($key, $value);
        }
        $request->setAttribute('route', $route);

        return $this->pipeline->handle($request, $route->middleware, function (Request $request) use ($route): Response {
            [$class, $method] = $route->handler;

            return $this->container->get($class)->{$method}($request);
        });
    }
}
