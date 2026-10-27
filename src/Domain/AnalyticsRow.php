<?php

declare(strict_types=1);

namespace App\Domain;

/** One line of an analytics breakdown table (a job, a page, a referrer, a search term...). */
final class AnalyticsRow
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly AnalyticsMetrics $metrics,
        public readonly ?string $href = null,
        public readonly ?string $note = null,
    ) {
    }
}
