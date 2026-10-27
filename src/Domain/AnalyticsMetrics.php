<?php

declare(strict_types=1);

namespace App\Domain;

/** Summed analytics counters for a day, a range or one breakdown row. */
final class AnalyticsMetrics
{
    public function __construct(
        public readonly int $pageviews = 0,
        public readonly int $visitors = 0,
        public readonly int $applies = 0,
        public readonly int $searches = 0,
        public readonly int $zeroResults = 0,
    ) {
    }

    /** @param array<string, mixed> $row columns as named in analytics_daily */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) ($row['pageviews'] ?? 0),
            (int) ($row['visitors'] ?? 0),
            (int) ($row['applies'] ?? 0),
            (int) ($row['searches'] ?? 0),
            (int) ($row['zero_results'] ?? 0),
        );
    }

    /** Apply clicks per page view, 0..1; for a job row that is applies per job view. */
    public function applyRate(): float
    {
        return $this->pageviews === 0 ? 0.0 : $this->applies / $this->pageviews;
    }

    public function zeroResultRate(): float
    {
        return $this->searches === 0 ? 0.0 : $this->zeroResults / $this->searches;
    }
}
