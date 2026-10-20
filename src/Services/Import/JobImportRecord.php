<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Domain\ExperienceLevel;
use App\Domain\JobType;

/** One job as delivered by a JobSourceInterface, before it has a category id or slug. */
final class JobImportRecord
{
    /**
     * @param list<string> $responsibilities
     * @param list<string> $requirements
     * @param list<string> $skills
     */
    public function __construct(
        public readonly string $title,
        public readonly string $company,
        public readonly string $location,
        public readonly bool $isRemote,
        public readonly JobType $jobType,
        public readonly ExperienceLevel $experienceLevel,
        public readonly string $categoryName,
        public readonly ?int $salaryMin,
        public readonly ?int $salaryMax,
        public readonly string $summary,
        public readonly array $responsibilities,
        public readonly array $requirements,
        public readonly array $skills,
        public readonly int $publishedDaysAgo,
    ) {
    }
}
