<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\Actor;
use App\Domain\DateRange;

interface AnalyticsExportServiceInterface
{
    /** @return list<string> tables that can be exported: the breakdowns plus "daily" (one row per day) */
    public function tables(): array;

    /**
     * Writes one analytics table for $range as CSV to $handle and records the export in the audit log.
     * @param resource $handle
     * @return int data rows written
     */
    public function export(string $table, DateRange $range, $handle, Actor $actor): int;
}
