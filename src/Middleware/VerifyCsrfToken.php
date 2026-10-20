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

    public function __construct(private readonly Csrf $csrf)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        if (!in_array($request->method, self::SAFE_METHODS, true)) {
            $submitted = $request->input(Csrf::FIELD) ?? $request->header(Csrf::HEADER);
            $secret = $request->attribute('csrf_cookie_secret');

            if (!$this->csrf->isValid(is_string($secret) ? $secret : null, is_string($submitted) ? $submitted : null)) {
                throw new HttpException(419);
            }
        }

        return $next($request);
    }
}
