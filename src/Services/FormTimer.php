<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppKey;

/**
 * Spam check for public forms: the form carries a signed "rendered at" time, and submissions
 * that come back faster than a person could type (or with a forged/old stamp) are treated as bots.
 */
final class FormTimer
{
    public const FIELD = '_ts';
    public const MIN_SECONDS = 3;
    public const MAX_AGE_SECONDS = 86400;

    private readonly string $key;

    public function __construct(AppKey $appKey)
    {
        $this->key = $appKey->derive(AppKey::SUBKEY_FORMS);
    }

    public function issue(?int $now = null): string
    {
        $time = (string) ($now ?? time());

        return $time . '.' . hash_hmac('sha256', $time, $this->key);
    }

    public function isHuman(?string $stamp, ?int $now = null): bool
    {
        if ($stamp === null || preg_match('/^(\d{9,11})\.([a-f0-9]{64})$/', $stamp, $m) !== 1) {
            return false;
        }
        if (!hash_equals(hash_hmac('sha256', $m[1], $this->key), $m[2])) {
            return false;
        }
        $elapsed = ($now ?? time()) - (int) $m[1];

        return $elapsed >= self::MIN_SECONDS && $elapsed <= self::MAX_AGE_SECONDS;
    }
}
