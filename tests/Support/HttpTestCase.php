<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Csrf;
use App\Core\Http\Kernel;
use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Sends requests through the real kernel (all middleware included) without a web server.
 * Keeps a cookie jar between requests, like a browser.
 */
abstract class HttpTestCase extends DatabaseTestCase
{
    /** @var array<string, string> */
    protected array $cookies = [];

    /** @param array<string, mixed> $query */
    protected function get(string $path, array $query = []): Response
    {
        $parts = explode('?', $path, 2);
        if (isset($parts[1])) {
            parse_str($parts[1], $fromUrl);
            $query = [...$fromUrl, ...$query];
        }

        return $this->send(new Request('GET', Request::normalisePath($parts[0]), $query, [], [], [], $this->cookies, $this->server()));
    }

    /**
     * Posts with a valid CSRF token unless $withToken is false.
     * @param array<string, mixed> $data
     * @param array<string, mixed> $files
     */
    protected function post(string $path, array $data = [], bool $withToken = true, array $files = []): Response
    {
        if ($withToken && !array_key_exists(Csrf::FIELD, $data)) {
            $data[Csrf::FIELD] = $this->csrfToken();
        }

        return $this->send(new Request('POST', Request::normalisePath($path), [], $data, $files, [], $this->cookies, $this->server()));
    }

    protected function csrfToken(): string
    {
        if (!isset($this->cookies[Csrf::COOKIE])) {
            $this->get('/health');
        }

        return $this->container->get(Csrf::class)->tokenFor($this->cookies[Csrf::COOKIE]);
    }

    protected function assertStatus(int $expected, Response $response): void
    {
        $this->assertSame($expected, $response->status(), 'Unexpected status. Body: ' . substr($response->body(), 0, 500));
    }

    protected function assertRedirect(string $location, Response $response): void
    {
        $this->assertContains($response->status(), [301, 302, 303]);
        $this->assertSame($location, $response->header('Location'));
    }

    /** @return array<string, string> */
    private function server(): array
    {
        return ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'phpunit'];
    }

    private function send(Request $request): Response
    {
        $response = $this->container->get(Kernel::class)->handle($request);
        foreach ($response->cookies() as $cookie) {
            if ($cookie['value'] === '' || ($cookie['options']['expires'] ?? 0) !== 0 && $cookie['options']['expires'] < time()) {
                unset($this->cookies[$cookie['name']]);
            } else {
                $this->cookies[$cookie['name']] = $cookie['value'];
            }
        }

        return $response;
    }
}
