<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Random URL-safe tokens for confirmation and unsubscribe links. Confirmation tokens are only
 * ever stored as hashes, so a database leak does not let anyone confirm subscriptions.
 */
final class TokenService
{
    /** 32 random bytes, base64url without padding: 43 characters, 256 bits of entropy. */
    public function generate(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public function looksValid(string $token): bool
    {
        return preg_match('/^[A-Za-z0-9_-]{43}$/', $token) === 1;
    }
}
