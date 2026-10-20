<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\PgArray;
use App\Domain\ExperienceLevel;
use App\Domain\Job;
use App\Domain\JobStatus;
use App\Domain\JobType;
use DateTimeImmutable;
use DateTimeZone;

/** Converts a jobs row (joined with its category) into a Job. */
final class JobRowMapper
{
    public const SELECT = 'j.id, j.title, j.slug, j.company, j.location, j.is_remote, j.job_type, j.experience_level,
        j.category_id, c.name AS category_name, c.slug AS category_slug, j.salary_min, j.salary_max, j.currency,
        j.summary, j.responsibilities, j.requirements, j.skills, j.apply_url, j.apply_email, j.status,
        j.published_at, j.expires_at, j.deleted_at, j.created_at, j.updated_at';

    public const FROM = 'jobs j JOIN job_categories c ON c.id = j.category_id';

    /** @param array<string, mixed> $row */
    public static function map(array $row): Job
    {
        return new Job(
            (int) $row['id'],
            (string) $row['title'],
            (string) $row['slug'],
            (string) $row['company'],
            (string) $row['location'],
            (bool) $row['is_remote'],
            JobType::from((string) $row['job_type']),
            ExperienceLevel::from((string) $row['experience_level']),
            (int) $row['category_id'],
            (string) $row['category_name'],
            (string) $row['category_slug'],
            $row['salary_min'] === null ? null : (int) $row['salary_min'],
            $row['salary_max'] === null ? null : (int) $row['salary_max'],
            (string) $row['currency'],
            (string) $row['summary'],
            self::list($row['responsibilities']),
            self::list($row['requirements']),
            PgArray::toList(is_string($row['skills']) ? $row['skills'] : null),
            $row['apply_url'] === null ? null : (string) $row['apply_url'],
            $row['apply_email'] === null ? null : (string) $row['apply_email'],
            JobStatus::from((string) $row['status']),
            self::date($row['published_at']),
            self::date($row['expires_at']),
            self::date($row['deleted_at']),
            self::date($row['created_at']) ?? new DateTimeImmutable(),
            self::date($row['updated_at']) ?? new DateTimeImmutable(),
        );
    }

    public static function date(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }

    public static function toDb(?DateTimeImmutable $date): ?string
    {
        return $date?->format(DATE_ATOM);
    }

    /** @return list<string> */
    private static function list(mixed $json): array
    {
        $decoded = is_string($json) ? json_decode($json, true) : null;

        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }
}
