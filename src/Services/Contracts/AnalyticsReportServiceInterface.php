<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\AnalyticsMetrics;
use App\Domain\AnalyticsRow;
use App\Domain\DateRange;
use DateTimeImmutable;

/** Read side of analytics for the back office. Every method first brings the daily totals up to date. */
interface AnalyticsReportServiceInterface
{
    /** Breakdown tables, by name (also the CSV export names). */
    public const TABLES = [
        'jobs', 'categories', 'pages', 'page_types', 'referrers', 'sources', 'campaigns',
        'devices', 'searches', 'zero_searches', 'filters', 'channels',
    ];

    /**
     * Everything the analytics page shows for $range.
     * @return array{
     *     totals: AnalyticsMetrics, previous: AnalyticsMetrics,
     *     jobViews: int, previousJobViews: int,
     *     series: list<array{day: DateTimeImmutable, metrics: AnalyticsMetrics}>,
     *     subscribers: array{signups: int, confirmations: int, unsubscribes: int},
     *     previousSubscribers: array{signups: int, confirmations: int, unsubscribes: int},
     *     tables: array<string, list<AnalyticsRow>>
     * }
     */
    public function overview(DateRange $range, int $rowsPerTable = 10): array;

    /**
     * Headline numbers for the dashboard.
     * @return array{totals: AnalyticsMetrics, previous: AnalyticsMetrics, jobViews: int, previousJobViews: int}
     */
    public function summary(DateRange $range): array;

    /**
     * Views and apply clicks for one job.
     * @return array{totals: AnalyticsMetrics, series: list<array{day: DateTimeImmutable, metrics: AnalyticsMetrics}>}
     */
    public function forJob(int $jobId, DateRange $range): array;

    /**
     * One breakdown table in full (no row limit), for CSV export.
     * @param string $table one of self::TABLES
     * @return list<AnalyticsRow>
     */
    public function table(string $table, DateRange $range, ?int $limit = null): array;

    /**
     * Visitors, page views, apply clicks and searches for each day of $range (days without data are zeros).
     * @return list<array{day: DateTimeImmutable, metrics: AnalyticsMetrics}>
     */
    public function series(DateRange $range): array;
}
