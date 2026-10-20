<?php

declare(strict_types=1);

namespace App\Core\Http;

/**
 * Immutable view of the incoming HTTP request, plus a mutable attribute bag
 * (route parameters, session) filled in by the kernel and middleware.
 */
final class Request
{
    /** @var array<string, mixed> */
    private array $attributes = [];

    /**
     * @param array<string, mixed> $query
     * @param array<string, mixed> $body
     * @param array<string, mixed> $files
     * @param array<string, string> $headers lower-cased names
     * @param array<string, string> $cookies
     * @param array<string, mixed> $server
     * @param list<string> $trustedProxies
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query = [],
        private readonly array $body = [],
        private readonly array $files = [],
        private readonly array $headers = [],
        private readonly array $cookies = [],
        private readonly array $server = [],
        private readonly array $trustedProxies = [],
    ) {
    }

    /** @param list<string> $trustedProxies */
    public static function fromGlobals(array $trustedProxies = []): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = (string) $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            self::normalisePath(is_string($path) ? $path : '/'),
            $_GET,
            $_POST,
            $_FILES,
            $headers,
            array_map('strval', $_COOKIE),
            $_SERVER,
            $trustedProxies,
        );
    }

    public static function normalisePath(string $path): string
    {
        $path = '/' . trim(rawurldecode($path), '/');

        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function queryAll(): array
    {
        return $this->query;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $default;
    }

    /** Trimmed string input; arrays and missing values become ''. */
    public function string(string $key): string
    {
        $value = $this->body[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return $this->body;
    }

    /** @return array<string, mixed>|null */
    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        return is_array($file) ? $file : null;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function cookie(string $name): ?string
    {
        return $this->cookies[$name] ?? null;
    }

    public function isMethod(string $method): bool
    {
        return $this->method === strtoupper($method);
    }

    public function isSecure(): bool
    {
        if ($this->fromTrustedProxy()) {
            $proto = $this->header('x-forwarded-proto');
            if ($proto !== null) {
                return strtolower(trim(explode(',', $proto)[0])) === 'https';
            }
        }
        $https = $this->server['HTTPS'] ?? '';

        return $https !== '' && strtolower((string) $https) !== 'off';
    }

    public function ip(): string
    {
        $remote = (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
        if (!$this->fromTrustedProxy()) {
            return $remote;
        }

        $forwarded = $this->header('x-forwarded-for');
        if ($forwarded === null) {
            return $remote;
        }

        $candidate = trim(explode(',', $forwarded)[0]);

        return filter_var($candidate, FILTER_VALIDATE_IP) !== false ? $candidate : $remote;
    }

    public function userAgent(): string
    {
        return $this->header('user-agent') ?? '';
    }

    public function fullUrlPath(): string
    {
        $query = http_build_query($this->query);

        return $this->path . ($query !== '' ? '?' . $query : '');
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    private function fromTrustedProxy(): bool
    {
        if ($this->trustedProxies === []) {
            return false;
        }
        if (in_array('*', $this->trustedProxies, true)) {
            return true;
        }

        return in_array((string) ($this->server['REMOTE_ADDR'] ?? ''), $this->trustedProxies, true);
    }
}
