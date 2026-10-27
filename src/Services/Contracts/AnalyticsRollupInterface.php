<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use DateTimeImmutable;

/** Keeps analytics_daily up to date and enforces the raw-event retention period. */
interface AnalyticsRollupInterface
{
    /**
     * Rolls up every day from the last rolled-up one through today (re-doing the last and today,
     * which may have new events), and deletes raw events past retention. Safe to run at any time;
     * skips if another process is already rolling up. Returns the number of days rolled up.
     */
    public function catchUp(?DateTimeImmutable $now = null): int;
}
