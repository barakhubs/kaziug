<?php

declare(strict_types=1);

namespace App\Core;

use App\Core\Http\Request;

/**
 * One place for cookie flags: always HttpOnly and SameSite=Lax, and Secure in production
 * or whenever the request arrived over HTTPS.
 */
final class CookieFactory
{
    public function __construct(private readonly Config $config)
    {
    }

    /** @return array<string, mixed> options for setcookie() */
    public function options(Request $request, int $expires = 0): array
    {
        return [
            'expires' => $expires,
            'path' => '/',
            'secure' => $this->config->bool('session.secure') || $request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }
}
