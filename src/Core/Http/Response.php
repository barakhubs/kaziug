<?php

declare(strict_types=1);

namespace App\Core\Http;

use Closure;

final class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    /** @var list<array{name: string, value: string, options: array<string, mixed>}> */
    private array $cookies = [];

    /**
     * @param string|Closure(): void $body a closure streams its output directly
     * @param array<string, string> $headers
     */
    public function __construct(
        private string|Closure $body = '',
        private int $status = 200,
        array $headers = [],
    ) {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** @param array<mixed> $data */
    public static function json(array $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            $status,
            ['Content-Type' => 'application/json'],
        );
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        if ($this->body instanceof Closure) {
            ob_start();
            ($this->body)();

            return (string) ob_get_clean();
        }

        return $this->body;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$this->normaliseName($name)] = $value;

        return $this;
    }

    public function header(string $name): ?string
    {
        return $this->headers[$this->normaliseName($name)] ?? null;
    }

    public function hasHeader(string $name): bool
    {
        return isset($this->headers[$this->normaliseName($name)]);
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    /** @param array<string, mixed> $options expires, path, secure, httponly, samesite */
    public function setCookie(string $name, string $value, array $options): self
    {
        $this->cookies[] = ['name' => $name, 'value' => $value, 'options' => $options];

        return $this;
    }

    /** @return list<array{name: string, value: string, options: array<string, mixed>}> */
    public function cookies(): array
    {
        return $this->cookies;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header("{$name}: {$value}");
            }
            foreach ($this->cookies as $cookie) {
                setcookie($cookie['name'], $cookie['value'], $cookie['options']);
            }
        }

        if ($this->body instanceof Closure) {
            ($this->body)();

            return;
        }
        echo $this->body;
    }

    private function normaliseName(string $name): string
    {
        return implode('-', array_map('ucfirst', explode('-', strtolower($name))));
    }
}
