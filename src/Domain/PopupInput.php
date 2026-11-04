<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

/** Validated popup fields from the admin form. bodyHtml is not sanitised yet: PopupService does that. */
final class PopupInput
{
    public function __construct(
        public readonly string $name,
        public readonly string $heading,
        public readonly string $bodyHtml,
        public readonly ?string $buttonLabel,
        public readonly ?string $buttonUrl,
        public readonly PopupTrigger $trigger,
        public readonly int $delaySeconds,
        public readonly ?DateTimeImmutable $startsAt,
        public readonly ?DateTimeImmutable $endsAt,
        public readonly string $imageAlt,
        /** Make this the one active popup (switching off any other). */
        public readonly bool $activate,
        /** Show it again to visitors who already saw an earlier version. */
        public readonly bool $showAgain,
    ) {
    }
}
