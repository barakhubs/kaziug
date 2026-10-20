<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\Job;
use App\Domain\JobSearchCriteria;

/** Public, read-only access: only published, non-deleted, non-expired jobs are ever returned. */
interface JobReadRepositoryInterface
{
    /** @return array{items: list<Job>, total: int} */
    public function searchPublished(JobSearchCriteria $criteria, int $limit, int $offset): array;

    public function findPublishedBySlug(string $slug): ?Job;

    /**
     * @param list<int> $ids
     * @return list<Job> in the order of $ids
     */
    public function findPublishedByIds(array $ids): array;

    /** @return list<string> distinct locations of visible jobs */
    public function publishedLocations(): array;

    /** @return list<array{location: string, count: int}> visible jobs per location, most first */
    public function locationCounts(): array;

    /** @return list<array{slug: string, updated_at: string}> every visible job, for the sitemap */
    public function sitemapEntries(): array;
}
