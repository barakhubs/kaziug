<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\AnalyticsRepositoryInterface;
use App\Repositories\Contracts\LockInterface;
use App\Services\Contracts\AnalyticsRollupInterface;
use DateTimeImmutable;
use DateTimeZone;

final class AnalyticsRollup implements AnalyticsRollupInterface
{
    /** Raw events (one row per page view) are kept this long, as the Privacy Policy states; daily totals stay. */
    public const RETENTION_DAYS = 365;
    private const LOCK = 'analytics:rollup';

    public function __construct(
        private readonly AnalyticsRepositoryInterface $repository,
        private readonly LockInterface $lock,
    ) {
    }

    public function catchUp(?DateTimeImmutable $now = null): int
    {
        if (!$this->lock->tryAcquire(self::LOCK)) {
            return 0;
        }

        try {
            $zone = new DateTimeZone(date_default_timezone_get());
            $today = ($now ?? new DateTimeImmutable())->setTimezone($zone)->setTime(0, 0);
            $cutoff = $today->modify('-' . self::RETENTION_DAYS . ' days');
            $this->repository->purgeEventsBefore($cutoff);

            $from = $this->repository->latestRolledDay()
                ?? $this->repository->earliestEventAt()?->setTimezone($zone)
                ?? $today;
            $from = max(new DateTimeImmutable($from->format('Y-m-d'), $zone), $cutoff);

            $days = 0;
            for ($day = $from; $day <= $today; $day = $day->modify('+1 day')) {
                $this->repository->rollUpDay($day, $day, $day->modify('+1 day'));
                $days++;
            }

            return $days;
        } finally {
            $this->lock->release(self::LOCK);
        }
    }
}
