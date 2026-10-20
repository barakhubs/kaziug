<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Actor;
use App\Domain\DomainException;
use App\Domain\Job;
use App\Domain\JobInput;
use App\Domain\JobStatus;
use App\Domain\NotFoundException;
use App\Domain\Paginated;
use App\Domain\Pagination;
use App\Domain\Slug;
use App\Domain\TableQuery;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\JobWriteRepositoryInterface;
use App\Repositories\Contracts\TransactionManagerInterface;
use App\Services\Contracts\AuditServiceInterface;
use App\Services\Contracts\JobServiceInterface;

final class JobService implements JobServiceInterface
{
    public function __construct(
        private readonly JobWriteRepositoryInterface $jobs,
        private readonly CategoryRepositoryInterface $categories,
        private readonly TransactionManagerInterface $tx,
        private readonly AuditServiceInterface $audit,
    ) {
    }

    public function search(array $filters, TableQuery $table): Paginated
    {
        $first = $this->jobs->adminSearch($filters, $table->perPage, $table->offset(), $table->sort, $table->direction);
        $pagination = new Pagination($table->page, $table->perPage, $first['total']);
        if ($pagination->page !== $table->page) {
            // Requested page was past the end: show the last page instead.
            $first = $this->jobs->adminSearch($filters, $table->perPage, $pagination->offset(), $table->sort, $table->direction);
        }

        return new Paginated($first['items'], $pagination);
    }

    public function get(int $id): Job
    {
        return $this->jobs->findById($id) ?? throw new NotFoundException('Job not found.');
    }

    public function create(JobInput $input, bool $publish, Actor $actor): Job
    {
        $this->assertCategoryExists($input->categoryId);
        $status = $publish ? JobStatus::Published : JobStatus::Draft;

        $id = $this->tx->transactional(function () use ($input, $status, $actor): int {
            $slug = Slug::unique(Slug::from($input->title), $this->jobs->slugExists(...));
            $id = $this->jobs->insert($input, $slug, $status, $status === JobStatus::Published ? new \DateTimeImmutable() : null);
            $this->jobs->recordStatusChange($id, null, $status, $actor->adminId);

            return $id;
        });
        $this->audit->record($actor, 'job.created', 'job', $id, ['title' => $input->title, 'status' => $status->value]);

        return $this->get($id);
    }

    public function update(int $id, JobInput $input, Actor $actor): void
    {
        $this->get($id);
        $this->assertCategoryExists($input->categoryId);
        $this->jobs->update($id, $input);
        $this->audit->record($actor, 'job.updated', 'job', $id, ['title' => $input->title]);
    }

    public function changeStatus(int $id, JobStatus $to, Actor $actor): void
    {
        $job = $this->get($id);
        if (!$job->status->canTransitionTo($to)) {
            throw new DomainException("A {$job->status->value} job can't be moved to {$to->value}.");
        }

        $this->applyTransition($job, $to, $actor);
        $this->audit->record($actor, 'job.status_changed', 'job', $id, ['from' => $job->status->value, 'to' => $to->value]);
    }

    public function bulkChangeStatus(array $ids, JobStatus $to, Actor $actor): array
    {
        $changed = [];
        $skipped = 0;
        foreach (array_unique(array_map('intval', $ids)) as $id) {
            $job = $this->jobs->findById($id);
            if ($job === null || !$job->status->canTransitionTo($to)) {
                $skipped++;
                continue;
            }
            $this->applyTransition($job, $to, $actor);
            $changed[] = $id;
        }

        if ($changed !== []) {
            $this->audit->record($actor, 'job.bulk_status_changed', 'job', null, ['to' => $to->value, 'ids' => $changed]);
        }

        return ['changed' => count($changed), 'skipped' => $skipped];
    }

    public function duplicate(int $id, Actor $actor): Job
    {
        $source = $this->get($id);
        $copy = $this->create(JobInput::fromJob($source)->withTitle($source->title), false, $actor);
        $this->audit->record($actor, 'job.duplicated', 'job', $copy->id, ['source_id' => $id]);

        return $copy;
    }

    public function delete(int $id, Actor $actor): void
    {
        $job = $this->get($id);
        $this->jobs->softDelete($id);
        $this->audit->record($actor, 'job.deleted', 'job', $id, ['title' => $job->title]);
    }

    public function history(int $id): array
    {
        return $this->jobs->statusHistory($id);
    }

    public function countByStatus(): array
    {
        return $this->jobs->countByStatus();
    }

    private function applyTransition(Job $job, JobStatus $to, Actor $actor): void
    {
        $this->tx->transactional(function () use ($job, $to, $actor): void {
            $this->jobs->updateStatus($job->id, $to);
            $this->jobs->recordStatusChange($job->id, $job->status, $to, $actor->adminId);
        });
    }

    private function assertCategoryExists(int $categoryId): void
    {
        if ($this->categories->findById($categoryId) === null) {
            throw new DomainException('Choose a category.', 'category_id');
        }
    }
}
