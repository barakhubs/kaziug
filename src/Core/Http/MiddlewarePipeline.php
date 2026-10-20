<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Container;

/**
 * Runs a request through middleware (outermost first) and then the final handler.
 */
final class MiddlewarePipeline
{
    public function __construct(private readonly Container $container)
    {
    }

    /**
     * @param list<class-string> $middleware
     * @param callable(Request): Response $handler
     */
    public function handle(Request $request, array $middleware, callable $handler): Response
    {
        $next = $handler;
        foreach (array_reverse($middleware) as $class) {
            $next = function (Request $request) use ($class, $next): Response {
                /** @var MiddlewareInterface $instance */
                $instance = $this->container->get($class);

                return $instance->process($request, $next);
            };
        }

        return $next($request);
    }
}
