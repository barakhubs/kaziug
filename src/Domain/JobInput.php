<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

/**
 * Validated job fields from the admin form (or an import source). Status is not part of
 * it: status only changes through JobService transitions.
 */
final class JobInput
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
        public readonly int $categoryId,
        public readonly ?int $salaryMin,
        public readonly ?int $salaryMax,
        public readonly string $currency,
        public readonly string $summary,
        public readonly array $responsibilities,
        public readonly array $requirements,
        public readonly array $skills,
        public readonly ?string $applyUrl,
        public readonly ?string $applyEmail,
        public readonly ?DateTimeImmutable $expiresAt,
        public readonly int $positions = 1,
        public readonly ?string $salaryScale = null,
        public readonly ?string $applicationInstructions = null,
        public readonly ?string $reportsTo = null,
    ) {
    }

    public static function fromJob(Job $job): self
    {
        return new self(
            $job->title, $job->company, $job->location, $job->isRemote, $job->jobType, $job->experienceLevel,
            $job->categoryId, $job->salaryMin, $job->salaryMax, $job->currency, $job->summary,
            $job->responsibilities, $job->requirements, $job->skills, $job->applyUrl, $job->applyEmail, $job->expiresAt,
            $job->positions, $job->salaryScale, $job->applicationInstructions, $job->reportsTo,
        );
    }

    public function withTitle(string $title): self
    {
        return new self(
            $title, $this->company, $this->location, $this->isRemote, $this->jobType, $this->experienceLevel,
            $this->categoryId, $this->salaryMin, $this->salaryMax, $this->currency, $this->summary,
            $this->responsibilities, $this->requirements, $this->skills, $this->applyUrl, $this->applyEmail, $this->expiresAt,
            $this->positions, $this->salaryScale, $this->applicationInstructions, $this->reportsTo,
        );
    }
}
