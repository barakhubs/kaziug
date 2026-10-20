<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Repositories\Contracts\ThrottleRepositoryInterface;
use DateTimeImmutable;

final class InMemoryThrottleRepository implements ThrottleRepositoryInterface
{
    /** @var array<string, int> */
    public array $counts = [];

    /** @var array<string, int> unix time */
    public array $locks = [];

    public function lockedUntil(string $key): ?DateTimeImmutable
    {
        $until = $this->locks[$key] ?? 0;

        return $until > time() ? (new DateTimeImmutable())->setTimestamp($until) : null;
    }

    public function recordFailure(string $key, int $windowSeconds): int
    {
        return $this->counts[$key] = ($this->counts[$key] ?? 0) + 1;
    }

    public function lock(string $key, int $seconds): void
    {
        $this->locks[$key] = time() + $seconds;
    }

    public function clear(string $key): void
    {
        unset($this->counts[$key], $this->locks[$key]);
    }

    /** Simulates the lock expiring without waiting. */
    public function expireLocks(): void
    {
        $this->locks = [];
    }
}
