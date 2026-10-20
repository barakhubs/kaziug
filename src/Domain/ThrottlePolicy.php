<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Progressive lockout: the first $freeAttempts failures inside $windowSeconds are free;
 * each further failure locks the key for base * 2^(n-1) seconds, capped at $maxLockSeconds.
 */
final class ThrottlePolicy
{
    public function __construct(
        public readonly int $freeAttempts,
        public readonly int $windowSeconds,
        public readonly int $baseLockSeconds,
        public readonly int $maxLockSeconds,
    ) {
    }

    /** Seconds to lock after the $failureCount-th failure (0 = no lock). */
    public function lockSecondsFor(int $failureCount): int
    {
        $over = $failureCount - $this->freeAttempts;
        if ($over <= 0) {
            return 0;
        }

        return (int) min($this->maxLockSeconds, $this->baseLockSeconds * (2 ** min($over - 1, 20)));
    }

    /** Admin login, per email: 5 free tries, then 1 min, 2 min, 4 min ... up to 1 hour. */
    public static function loginPerEmail(): self
    {
        return new self(5, 3600, 60, 3600);
    }

    /** Admin login, per IP: more generous (shared IPs), same progression. */
    public static function loginPerIp(): self
    {
        return new self(20, 3600, 60, 3600);
    }

    /** Contact and CV-help forms, per IP: 5 messages per hour, then 15 min and up. */
    public static function contactPerIp(): self
    {
        return new self(5, 3600, 900, 86400);
    }

    /** Newsletter subscribe form, per IP: 5 submissions per hour, then 15 min and up. */
    public static function subscribePerIp(): self
    {
        return new self(5, 3600, 900, 86400);
    }
}
