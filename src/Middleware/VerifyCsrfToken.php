<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Http\HttpException;
use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Rejects every state-changing request (anything but GET/HEAD/OPTIONS) whose token,
 * from the _token field or the X-CSRF-Token header, does not match the browser's cookie.
 */
final class VerifyCsrfToken implements MiddlewareInterface
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    /**
     * RFC 8058 one-click unsubscribe: mail clients POST to the link in the List-Unsubscribe
     * header without any page or cookie. The 256-bit token in the URL is the authorisation,
     * and the only effect is unsubscribing that one address.
     */
    private const EXEMPT = ['#^/unsubscribe/[A-Za-z0-9_-]{43}$#'];

    public function __construct(private readonly Csrf $csrf)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if (!in_array($request->method, self::SAFE_METHODS, true) && !$this->isExempt($request->path)) {
            $submitted = $request->input(Csrf::FIELD) ?? $request->header(Csrf::HEADER);
            $secret = $request->attribute('csrf_cookie_secret');

            if (!$this->csrf->isValid(is_string($secret) ? $secret : null, is_string($submitted) ? $submitted : null)) {
                throw new HttpException(419);
            }
        }

        return $next($request);
    }

    private function isExempt(string $path): bool
    {
        foreach (self::EXEMPT as $pattern) {
            if (preg_match($pattern, $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
