<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Domain\ExperienceLevel;
use App\Domain\JobType;
use DateTimeImmutable;
use RuntimeException;

/**
 * Sample jobs from database/seeds/sample_jobs.json. Optional keys: salaryScale, posts
 * (number of positions), deadline (Y-m-d), applicationInstruction, applicationUrl, applicationEmail and reportsTo.
 */
final class SampleJobSource implements JobSourceInterface
{
    public function __construct(private readonly string $file)
    {
    }

    public function name(): string
    {
        return 'sample jobs';
    }

    public function records(): iterable
    {
        $data = json_decode((string) @file_get_contents($this->file), true);
        if (!is_array($data)) {
            throw new RuntimeException("Could not read sample jobs from {$this->file}");
        }

        foreach ($data as $job) {
            yield new JobImportRecord(
                (string) $job['title'],
                (string) $job['company'],
                (string) $job['location'],
                (bool) $job['remote'],
                JobType::fromLabel((string) $job['type']),
                ExperienceLevel::from(strtolower((string) $job['exp'])),
                (string) $job['category'],
                isset($job['salaryMin']) ? (int) $job['salaryMin'] : null,
                isset($job['salaryMax']) ? (int) $job['salaryMax'] : null,
                (string) $job['summary'],
                array_map('strval', $job['responsibilities'] ?? []),
                array_map('strval', $job['requirements'] ?? []),
                array_map('strval', $job['skills'] ?? []),
                (int) ($job['daysAgo'] ?? 0),
                // Three weeks to apply from the posting date, but at least two more days.
                max(2, 21 - (int) ($job['daysAgo'] ?? 0)),
                max(1, (int) ($job['posts'] ?? 1)),
                self::text($job['salaryScale'] ?? null),
                isset($job['deadline']) ? new DateTimeImmutable((string) $job['deadline']) : null,
                self::text($job['applicationInstruction'] ?? null),
                filter_var(self::text($job['applicationUrl'] ?? null), FILTER_VALIDATE_URL) ?: null,
                // A malformed address is dropped rather than published.
                filter_var(self::text($job['applicationEmail'] ?? null), FILTER_VALIDATE_EMAIL) ?: null,
                self::text($job['reportsTo'] ?? null),
            );
        }
    }

    private static function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
