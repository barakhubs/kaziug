<?php

declare(strict_types=1);

namespace App\Domain;

/** One row for analytics_events: a page view or an apply click, already anonymised. */
final class AnalyticsEvent
{
    public function __construct(
        public readonly string $visitorHash,
        public readonly string $path,
        public readonly string $device,
        public readonly PageContext $page,
        public readonly ?ApplyChannel $applyChannel = null,
        public readonly ?string $referrerHost = null,
        public readonly ?string $utmSource = null,
        public readonly ?string $utmMedium = null,
        public readonly ?string $utmCampaign = null,
    ) {
    }

    public function type(): string
    {
        return $this->applyChannel === null ? 'pageview' : 'apply_click';
    }
}
