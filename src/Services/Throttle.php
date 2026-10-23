<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\ThrottlePolicy;
use App\Repositories\Contracts\ThrottleRepositoryInterface;
use App\Services\Contracts\ThrottleInterface;

final class Throttle implements ThrottleInterface
{
    /** Rate-limit counters (keyed by IP or hashed email) are kept at most this long after their last use. */
    public const RETENTION_SECONDS = 2 * 86400;
    private const PURGE_ONE_IN = 50;

    public function __construct(private readonly ThrottleRepositoryInterface $repository)
    {
    }

    public function lockedFor(string $key): int
    {
        $until = $this->repository->lockedUntil($key);

        return $until === null ? 0 : max(1, $until->getTimestamp() - time());
    }

    public function hit(string $key, ThrottlePolicy $policy): int
    {
        // Keys hold IP addresses: old counters are removed (as the privacy policy promises).
        if (random_int(1, self::PURGE_ONE_IN) === 1) {
            $this->repository->purgeOlderThan(self::RETENTION_SECONDS);
        }
        $count = $this->repository->recordFailure($key, $policy->windowSeconds);
        $lock = $policy->lockSecondsFor($count);
        if ($lock > 0) {
            $this->repository->lock($key, $lock);
        }

        return $lock;
    }

    public function clear(string $key): void
    {
        $this->repository->clear($key);
    }
}
