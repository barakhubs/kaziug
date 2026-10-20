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
}
