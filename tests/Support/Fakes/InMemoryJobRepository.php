<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Job;
use App\Domain\JobInput;
use App\Domain\JobStatus;
use App\Repositories\Contracts\JobWriteRepositoryInterface;
use DateTimeImmutable;

final class InMemoryJobRepository implements JobWriteRepositoryInterface
{
    /** @var array<int, Job> */
    public array $jobs = [];

    /** @var list<array{job: int, from: ?string, to: string, by: ?int}> */
    public array $history = [];

    private int $nextId = 1;

    public function adminSearch(array $filters, int $limit, int $offset, string $sort = 'updated', string $direction = 'desc'): array
    {
        $items = array_values(array_filter($this->jobs, static fn (Job $j): bool => $j->deletedAt === null
            && (($filters['status'] ?? '') === '' || $j->status->value === $filters['status'])));

        return ['items' => array_slice($items, $offset, $limit), 'total' => count($items)];
    }

    public function findById(int $id): ?Job
    {
        $job = $this->jobs[$id] ?? null;

        return $job?->deletedAt === null ? $job : null;
    }

    public function slugExists(string $slug): bool
    {
        foreach ($this->jobs as $job) {
            if ($job->slug === $slug) {
                return true;
            }
        }

        return false;
    }

    public function insert(JobInput $input, string $slug, JobStatus $status, ?DateTimeImmutable $publishedAt): int
    {
        $id = $this->nextId++;
        $this->jobs[$id] = $this->build($id, $input, $slug, $status, $publishedAt, null);

        return $id;
    }

    public function update(int $id, JobInput $input): void
    {
        $job = $this->jobs[$id];
        $this->jobs[$id] = $this->build($id, $input, $job->slug, $job->status, $job->publishedAt, null);
    }

    public function updateStatus(int $id, JobStatus $to): void
    {
        $job = $this->jobs[$id];
        $publishedAt = $to === JobStatus::Published ? ($job->publishedAt ?? new DateTimeImmutable()) : $job->publishedAt;
        $this->jobs[$id] = $this->build($id, JobInput::fromJob($job), $job->slug, $to, $publishedAt, null);
    }

    public function softDelete(int $id): void
    {
        $job = $this->jobs[$id];
        $this->jobs[$id] = $this->build($id, JobInput::fromJob($job), $job->slug, $job->status, $job->publishedAt, new DateTimeImmutable());
    }

    public function recordStatusChange(int $jobId, ?JobStatus $from, JobStatus $to, ?int $adminId): void
    {
        $this->history[] = ['job' => $jobId, 'from' => $from?->value, 'to' => $to->value, 'by' => $adminId];
    }

    public function statusHistory(int $jobId): array
    {
        return [];
    }

    public function countByStatus(): array
    {
        $counts = array_fill_keys(JobStatus::values(), 0);
        foreach ($this->jobs as $job) {
            if ($job->deletedAt === null) {
                $counts[$job->status->value]++;
            }
        }

        return $counts;
    }

    private function build(int $id, JobInput $in, string $slug, JobStatus $status, ?DateTimeImmutable $publishedAt, ?DateTimeImmutable $deletedAt): Job
    {
        $now = new DateTimeImmutable();

        return new Job(
            $id, $in->title, $slug, $in->company, $in->location, $in->isRemote, $in->jobType, $in->experienceLevel,
            $in->categoryId, 'Category', 'category', $in->salaryMin, $in->salaryMax, $in->currency, $in->summary,
            $in->responsibilities, $in->requirements, $in->skills, $in->applyUrl, $in->applyEmail, $status,
            $publishedAt, $in->expiresAt, $deletedAt, $now, $now, $in->positions, $in->salaryScale,
            $in->applicationInstructions, $in->reportsTo,
        );
    }
}
