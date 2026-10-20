<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\ThrottlePolicy;

interface ThrottleInterface
{
    /** Seconds until the key is unlocked, 0 if it is not locked. */
    public function lockedFor(string $key): int;

    /** Records one hit; returns the lock (in seconds) it caused, 0 if none. */
    public function hit(string $key, ThrottlePolicy $policy): int;

    public function clear(string $key): void;
}
