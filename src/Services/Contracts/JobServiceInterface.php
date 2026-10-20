<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\Actor;
use App\Domain\Job;
use App\Domain\JobInput;
use App\Domain\JobStatus;
use App\Domain\Paginated;
use App\Domain\TableQuery;

/** Back-office job management. */
interface JobServiceInterface
{
    /**
     * @param array{q?: string, status?: string, category_id?: int} $filters
     * @return Paginated<Job>
     */
    public function search(array $filters, TableQuery $table): Paginated;

    public function get(int $id): Job;

    /** Creates a draft, or publishes immediately when $publish is true. */
    public function create(JobInput $input, bool $publish, Actor $actor): Job;

    public function update(int $id, JobInput $input, Actor $actor): void;

    /** @throws \App\Domain\DomainException when the transition is not allowed */
    public function changeStatus(int $id, JobStatus $to, Actor $actor): void;

    /**
     * Applies the transition to every job where it is allowed and skips the rest.
     * @param list<int> $ids
     * @return array{changed: int, skipped: int}
     */
    public function bulkChangeStatus(array $ids, JobStatus $to, Actor $actor): array;

    /** Copies a job into a new draft. */
    public function duplicate(int $id, Actor $actor): Job;

    /** Soft delete: the row stays (deleted_at) but disappears everywhere. */
    public function delete(int $id, Actor $actor): void;

    /** @return list<array{from_status: ?string, to_status: string, changed_by: ?string, changed_at: string}> */
    public function history(int $id): array;

    /** @return array<string, int> */
    public function countByStatus(): array;
}
