<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Signed double-submit CSRF protection. Each browser gets a random secret in an HttpOnly
 * cookie; forms carry HMAC(secret) under a key derived from APP_KEY. An attacker can neither
 * read the cookie nor compute the token without the key. No server-side state is needed, so
 * anonymous visitors don't create session rows just to see the subscribe form.
 */
final class Csrf
{
    public const FIELD = '_token';
    public const HEADER = 'x-csrf-token';
    public const COOKIE = 'kaziug_csrf';

    private readonly string $key;

    public function __construct(AppKey $appKey)
    {
        $this->key = $appKey->derive(AppKey::SUBKEY_CSRF);
    }

    public function newSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    public function tokenFor(string $secret): string
    {
        return hash_hmac('sha256', 'csrf|' . $secret, $this->key);
    }

    public function isValid(?string $secret, ?string $submitted): bool
    {
        if ($secret === null || $secret === '' || $submitted === null || $submitted === '') {
            return false;
        }

        return hash_equals($this->tokenFor($secret), $submitted);
    }
}
