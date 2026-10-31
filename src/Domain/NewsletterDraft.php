<?php

declare(strict_types=1);

namespace App\Domain;

/** Subject, preheader and body produced by a newsletter template, before the admin edits and saves it. */
final class NewsletterDraft
{
    public function __construct(
        public readonly string $subject,
        public readonly string $preheader,
        public readonly string $bodyHtml,
    ) {
    }
}
