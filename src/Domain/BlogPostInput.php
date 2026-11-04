<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

/** Validated blog post fields from the admin form. bodyHtml is not sanitised yet: BlogService does that. */
final class BlogPostInput
{
    public function __construct(
        public readonly string $title,
        /** Null derives the slug from the title. */
        public readonly ?string $slug,
        public readonly string $excerpt,
        public readonly string $metaDescription,
        public readonly string $bodyHtml,
        public readonly ?int $jobCategoryId,
        public readonly string $coverAlt,
        /** Null means "now" when publishing (or keep the current date). */
        public readonly ?DateTimeImmutable $publishAt,
    ) {
    }
}
