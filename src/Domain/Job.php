<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final class Job
{
    /**
     * @param list<string> $responsibilities
     * @param list<string> $requirements
     * @param list<string> $skills
     */
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $slug,
        public readonly string $company,
        public readonly string $location,
        public readonly bool $isRemote,
        public readonly JobType $jobType,
        public readonly ExperienceLevel $experienceLevel,
        public readonly int $categoryId,
        public readonly string $categoryName,
        public readonly string $categorySlug,
        public readonly ?int $salaryMin,
        public readonly ?int $salaryMax,
        public readonly string $currency,
        public readonly string $summary,
        public readonly array $responsibilities,
        public readonly array $requirements,
        public readonly array $skills,
        public readonly ?string $applyUrl,
        public readonly ?string $applyEmail,
        public readonly JobStatus $status,
        public readonly ?DateTimeImmutable $publishedAt,
        public readonly ?DateTimeImmutable $expiresAt,
        public readonly ?DateTimeImmutable $deletedAt,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
        /** How many people the employer is hiring for this role. */
        public readonly int $positions = 1,
        /** Public-sector salary scale such as "U4", shown instead of or next to amounts. */
        public readonly ?string $salaryScale = null,
    ) {
    }

    public function hasSalaryAmount(): bool
    {
        return $this->salaryMin !== null || $this->salaryMax !== null;
    }

    public function isExpired(?DateTimeImmutable $now = null): bool
    {
        return $this->expiresAt !== null && $this->expiresAt <= ($now ?? new DateTimeImmutable());
    }

    public function isPubliclyVisible(?DateTimeImmutable $now = null): bool
    {
        return $this->status === JobStatus::Published && $this->deletedAt === null && !$this->isExpired($now);
    }
}
