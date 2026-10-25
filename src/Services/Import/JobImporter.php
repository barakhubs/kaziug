<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Domain\Actor;
use App\Domain\JobInput;
use App\Domain\JobStatus;
use App\Domain\Slug;
use App\Repositories\Contracts\JobWriteRepositoryInterface;
use App\Repositories\Contracts\TransactionManagerInterface;
use App\Services\Contracts\CategoryServiceInterface;
use DateTimeImmutable;

/**
 * Imports published jobs from any JobSourceInterface. Idempotent: a job whose slug already
 * exists is skipped, so running the seed twice adds nothing.
 */
final class JobImporter
{
    public function __construct(
        private readonly JobWriteRepositoryInterface $jobs,
        private readonly CategoryServiceInterface $categories,
        private readonly TransactionManagerInterface $tx,
    ) {
    }

    /** @return array{created: int, skipped: int} */
    public function import(JobSourceInterface $source, Actor $actor): array
    {
        $created = 0;
        $skipped = 0;

        foreach ($source->records() as $record) {
            $slug = Slug::from($record->title . ' ' . $record->company);
            if ($this->jobs->slugExists($slug)) {
                $skipped++;
                continue;
            }

            $this->tx->transactional(function () use ($record, $slug, $actor): void {
                $category = $this->categories->ensure($record->categoryName, $actor);
                $input = new JobInput(
                    $record->title, $record->company, $record->location, $record->isRemote, $record->jobType,
                    $record->experienceLevel, $category->id, $record->salaryMin, $record->salaryMax, 'UGX',
                    $record->summary, $record->responsibilities, $record->requirements, $record->skills, null, null,
                    ($record->deadline ?? new DateTimeImmutable("+{$record->deadlineInDays} days"))->setTime(23, 59, 59),
                    $record->positions,
                    $record->salaryScale,
                    $record->applicationInstructions,
                );
                $publishedAt = new DateTimeImmutable("-{$record->publishedDaysAgo} days");
                $id = $this->jobs->insert($input, $slug, JobStatus::Published, $publishedAt);
                $this->jobs->recordStatusChange($id, null, JobStatus::Published, $actor->adminId);
            });
            $created++;
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
