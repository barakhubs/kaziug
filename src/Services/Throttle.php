<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\ThrottlePolicy;
use App\Repositories\Contracts\ThrottleRepositoryInterface;
use App\Services\Contracts\ThrottleInterface;

final class Throttle implements ThrottleInterface
{
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
