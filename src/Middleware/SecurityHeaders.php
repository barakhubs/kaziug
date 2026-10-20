<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;

/**
 * Adds security headers to every response. A response that already set a header
 * (e.g. the sandboxed newsletter preview) keeps its own value.
 */
final class SecurityHeaders implements MiddlewareInterface
{
    // No inline scripts or styles anywhere: all JS and CSS are served from /assets.
    private const CSP = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; "
        . "font-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; "
        . "frame-src 'self'; base-uri 'self'; object-src 'none'";

    public function __construct(private readonly Config $config)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        $defaults = [
            'Content-Security-Policy' => self::CSP,
            'X-Frame-Options' => 'DENY',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ];
        if ($this->config->isProduction() && $request->isSecure()) {
            $defaults['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }
        if (str_starts_with($request->path, '/admin')) {
            $defaults['Cache-Control'] = 'no-store';
            $defaults['X-Robots-Tag'] = 'noindex, nofollow';
        }

        foreach ($defaults as $name => $value) {
            if (!$response->hasHeader($name)) {
                $response->setHeader($name, $value);
            }
        }

        return $response;
    }
}
