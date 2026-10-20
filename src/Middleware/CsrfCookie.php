<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\CookieFactory;
use App\Core\Csrf;
use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Makes sure the browser has a CSRF secret cookie and exposes the matching form token
 * as the "csrf_token" request attribute. Validation happens in VerifyCsrfToken.
 */
final class CsrfCookie implements MiddlewareInterface
{
    public function __construct(
        private readonly Csrf $csrf,
        private readonly CookieFactory $cookies,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $existing = $request->cookie(Csrf::COOKIE);
        $valid = is_string($existing) && preg_match('/^[a-f0-9]{64}$/', $existing) === 1;
        $secret = $valid ? $existing : $this->csrf->newSecret();

        $request->setAttribute('csrf_cookie_secret', $valid ? $existing : null);
        self::apply($this->csrf, $request, $secret);

        $response = $next($request);

        $final = (string) $request->attribute('csrf_secret');
        if ($final !== $existing) {
            $response->setCookie(Csrf::COOKIE, $final, $this->cookies->options($request));
        }

        return $response;
    }

    /** Replaces the secret for the rest of this request and the cookie sent back (used after login). */
    public static function rotate(Csrf $csrf, Request $request): void
    {
        self::apply($csrf, $request, $csrf->newSecret());
    }

    private static function apply(Csrf $csrf, Request $request, string $secret): void
    {
        $request->setAttribute('csrf_secret', $secret);
        $request->setAttribute('csrf_token', $csrf->tokenFor($secret));
    }
}
