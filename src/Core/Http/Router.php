<?php

declare(strict_types=1);

namespace App\Core\Http;

use Closure;

/**
 * Maps method + path to a controller action. Patterns use {name} or {name:regex}.
 * Groups add a path prefix and middleware to every route declared inside them.
 */
final class Router
{
    /** @var list<Route> */
    private array $routes = [];

    private string $prefix = '';

    /** @var list<class-string> */
    private array $groupMiddleware = [];

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<class-string> $middleware
     */
    public function get(string $pattern, array $handler, array $middleware = []): Route
    {
        return $this->add('GET', $pattern, $handler, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<class-string> $middleware
     */
    public function post(string $pattern, array $handler, array $middleware = []): Route
    {
        return $this->add('POST', $pattern, $handler, $middleware);
    }

    /**
     * @param list<class-string> $middleware
     * @param Closure(Router): void $routes
     */
    public function group(string $prefix, array $middleware, Closure $routes): void
    {
        [$previousPrefix, $previousMiddleware] = [$this->prefix, $this->groupMiddleware];
        $this->prefix .= $prefix;
        $this->groupMiddleware = [...$this->groupMiddleware, ...$middleware];

        try {
            $routes($this);
        } finally {
            [$this->prefix, $this->groupMiddleware] = [$previousPrefix, $previousMiddleware];
        }
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<class-string> $middleware
     */
    public function add(string $method, string $pattern, array $handler, array $middleware = []): Route
    {
        $path = Request::normalisePath($this->prefix . $pattern);
        $route = new Route($method, $path, $handler, [...$this->groupMiddleware, ...$middleware]);
        $this->routes[] = $route;

        return $route;
    }

    /**
     * @return array{0: Route, 1: array<string, string>}
     * @throws HttpException 404 when nothing matches, 405 when only the method differs
     */
    public function match(string $method, string $path): array
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            $params = $route->matches($path);
            if ($params === null) {
                continue;
            }
            if ($route->method === $method || ($method === 'HEAD' && $route->method === 'GET')) {
                return [$route, $params];
            }
            $allowed[] = $route->method;
        }

        if ($allowed !== []) {
            throw new HttpException(405, '', ['Allow' => implode(', ', array_unique($allowed))]);
        }

        throw HttpException::notFound();
    }

    /** @return list<Route> */
    public function routes(): array
    {
        return $this->routes;
    }
}
