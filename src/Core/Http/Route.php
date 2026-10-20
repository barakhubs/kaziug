<?php

declare(strict_types=1);

namespace App\Core\Http;

final class Route
{
    private readonly string $regex;

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<class-string> $middleware
     */
    public function __construct(
        public readonly string $method,
        public readonly string $pattern,
        public readonly array $handler,
        public readonly array $middleware,
    ) {
        $this->regex = $this->compile($pattern);
    }

    /** @return array<string, string>|null route parameters, or null when the path does not match */
    public function matches(string $path): ?array
    {
        if (preg_match($this->regex, $path, $m) !== 1) {
            return null;
        }

        return array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    private function compile(string $pattern): string
    {
        $regex = preg_replace_callback(
            '/\{(\w+)(?::([^}]+))?\}/',
            static fn (array $m): string => '(?P<' . $m[1] . '>' . ($m[2] ?? '[^/]+') . ')',
            $pattern,
        );

        return '#^' . $regex . '$#';
    }
}
