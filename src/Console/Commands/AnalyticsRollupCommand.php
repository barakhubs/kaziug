<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Console\Io;
use App\Services\AnalyticsRollup;
use App\Services\Contracts\AnalyticsRollupInterface;

/**
 * Brings the daily analytics totals up to date and deletes raw events past retention. The
 * analytics pages do the same on demand, so scheduling this (e.g. nightly) only keeps the
 * retention promise on schedule even when nobody opens them.
 */
final class AnalyticsRollupCommand implements CommandInterface
{
    public function __construct(private readonly AnalyticsRollupInterface $rollup)
    {
    }

    public function name(): string
    {
        return 'analytics:rollup';
    }

    public function description(): string
    {
        return 'Update daily analytics totals and delete raw events older than ' . AnalyticsRollup::RETENTION_DAYS . ' days';
    }

    public function run(array $args, Io $io): int
    {
        $days = $this->rollup->catchUp();
        $io->line($days === 0 ? 'Another rollup is running; skipped.' : "Rolled up {$days} day(s).");

        return 0;
    }
}
