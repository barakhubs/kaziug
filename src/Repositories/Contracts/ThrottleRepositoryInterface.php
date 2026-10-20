<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use DateTimeImmutable;

/** Failure counters in login_attempts, shared by login and subscribe throttling. */
interface ThrottleRepositoryInterface
{
    public function lockedUntil(string $key): ?DateTimeImmutable;

    /** Counts one failure; the count restarts at 1 once the previous window has expired. Returns the new count. */
    public function recordFailure(string $key, int $windowSeconds): int;

    public function lock(string $key, int $seconds): void;

    public function clear(string $key): void;
}
