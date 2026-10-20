<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\Job;
use App\Domain\JobSearchCriteria;
use App\Domain\Paginated;

/** Public job queries. Only ever returns published, non-expired, non-deleted jobs. */
interface JobSearchServiceInterface
{
    /** @return Paginated<Job> */
    public function search(JobSearchCriteria $criteria, int $perPage): Paginated;

    /** @throws \App\Domain\NotFoundException */
    public function findBySlug(string $slug): Job;

    /**
     * @param list<int> $ids
     * @return list<Job>
     */
    public function savedJobs(array $ids): array;

    /** @return list<string> */
    public function locations(): array;

    /**
     * Cities with open jobs, each with the slug of its landing page (/jobs-in/{slug}).
     * @return list<array{name: string, slug: string, count: int}>
     */
    public function cities(): array;

    /** @return array{name: string, slug: string, count: int}|null */
    public function findCity(string $slug): ?array;

    /** @return list<array{slug: string, updated_at: string}> */
    public function sitemapEntries(): array;

    /** @return list<Job> newest first */
    public function latest(int $limit): array;
}
