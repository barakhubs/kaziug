<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * What a public page showed, for analytics: set by the controller as a request attribute and
 * read by the RecordPageView middleware after the page is rendered.
 */
final class PageContext
{
    public const ATTRIBUTE = 'analytics.page';

    /** @param list<string> $searchFilters names of the filters used, e.g. ['location', 'type'] */
    public function __construct(
        public readonly PageType $pageType = PageType::Page,
        public readonly ?int $jobId = null,
        public readonly ?int $categoryId = null,
        public readonly ?string $searchQuery = null,
        public readonly array $searchFilters = [],
        public readonly ?int $resultCount = null,
    ) {
    }

    public static function job(Job $job): self
    {
        return new self(PageType::Job, $job->id, $job->categoryId);
    }

    public function isSearch(): bool
    {
        return $this->resultCount !== null;
    }
}
