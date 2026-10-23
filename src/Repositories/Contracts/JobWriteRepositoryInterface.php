<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\Job;
use App\Domain\JobInput;
use App\Domain\JobStatus;
use DateTimeImmutable;

/** Back-office access to jobs (all statuses). Public reads live in JobReadRepositoryInterface. */
interface JobWriteRepositoryInterface
{
    public const SORTS = ['title', 'status', 'posted', 'deadline', 'updated'];

    /**
     * @param array{q?: string, status?: string, category_id?: int} $filters
     * @param string $sort one of self::SORTS
     * @param 'asc'|'desc' $direction
     * @return array{items: list<Job>, total: int}
     */
    public function adminSearch(array $filters, int $limit, int $offset, string $sort = 'updated', string $direction = 'desc'): array;

    public function findById(int $id): ?Job;

    public function slugExists(string $slug): bool;

    public function insert(JobInput $input, string $slug, JobStatus $status, ?DateTimeImmutable $publishedAt): int;

    public function update(int $id, JobInput $input): void;

    /** Sets the status; sets published_at to now() when publishing a job that was never published. */
    public function updateStatus(int $id, JobStatus $to): void;

    public function softDelete(int $id): void;

    public function recordStatusChange(int $jobId, ?JobStatus $from, JobStatus $to, ?int $adminId): void;

    /** @return list<array{from_status: ?string, to_status: string, changed_by: ?string, changed_at: string}> */
    public function statusHistory(int $jobId): array;

    /** @return array<string, int> status => count of non-deleted jobs */
    public function countByStatus(): array;
}
