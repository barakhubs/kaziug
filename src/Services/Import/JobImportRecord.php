<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Domain\ExperienceLevel;
use App\Domain\JobType;
use DateTimeImmutable;

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
        /** Days from today until applications close (end of that day). */
        public readonly int $deadlineInDays = 30,
        public readonly int $positions = 1,
        public readonly ?string $salaryScale = null,
        /** A fixed closing day from the advert; when set it wins over $deadlineInDays. */
        public readonly ?DateTimeImmutable $deadline = null,
        public readonly ?string $applicationInstructions = null,
        public readonly ?string $applyUrl = null,
        public readonly ?string $applyEmail = null,
        public readonly ?string $reportsTo = null,
    ) {
    }
}
