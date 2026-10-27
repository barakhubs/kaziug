<?php

declare(strict_types=1);

namespace App\Services\Contracts;

/** Read-only summary for the back-office dashboard. */
interface DashboardServiceInterface
{
    /**
     * @return array{
     *   jobs: array<string, int>,
     *   subscribers: array<string, int>,
     *   latestNewsletter: ?\App\Domain\Newsletter,
     *   latestCounts: ?\App\Domain\DeliveryCounts,
     *   categories: list<\App\Domain\Category>,
     *   recentAudit: list<array<string, mixed>>,
     *   mailConfigured: bool,
     *   analytics: array{totals: \App\Domain\AnalyticsMetrics, previous: \App\Domain\AnalyticsMetrics, jobViews: int, previousJobViews: int}
     * }
     */
    public function summary(): array;
}
