<?php

declare(strict_types=1);

namespace App\Core;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * Display formatting shared by templates and emails. Returns plain text; templates still escape it.
 */
final class Format
{
    /** "UGX 3.5M" for millions, "UGX 800,000" below, as in the prototype. */
    public static function money(int $amount, string $currency = 'UGX'): string
    {
        if ($amount >= 1_000_000) {
            return $currency . ' ' . rtrim(rtrim(number_format($amount / 1_000_000, 1, '.', ''), '0'), '.') . 'M';
        }

        return $currency . ' ' . number_format($amount);
    }

    public static function salary(?int $min, ?int $max, string $currency): string
    {
        if ($min === null && $max === null) {
            return 'Salary not disclosed';
        }
        if ($min === null) {
            return 'Up to ' . self::money((int) $max, $currency) . ' / month';
        }
        $range = $max !== null && $max !== $min ? ' – ' . self::money($max, $currency) : '';

        return self::money($min, $currency) . $range . ' / month';
    }

    /** Shortens text for meta descriptions: whole words only, ending in "…" when cut. */
    public static function excerpt(string $text, int $max = 155): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if (mb_strlen($text) <= $max) {
            return $text;
        }
        $cut = mb_substr($text, 0, $max - 1);
        $space = mb_strrpos($cut, ' ');
        if ($space !== false && $space > $max * 0.6) {
            $cut = mb_substr($cut, 0, $space);
        }

        return rtrim($cut, " ,.;:-–") . '…';
    }

    /** One plain sentence with a job's key facts, easy for people and AI answers to quote. */
    public static function jobFacts(\App\Domain\Job $job): string
    {
        $where = $job->location . ', Uganda' . ($job->isRemote ? ' (remote-friendly)' : '');

        return sprintf(
            '%s %s-level %s role at %s in %s. Salary: %s.',
            $job->jobType->label(),
            strtolower($job->experienceLevel->label()),
            $job->categoryName,
            $job->company,
            $where,
            $job->salaryMin === null && $job->salaryMax === null ? 'not disclosed' : self::salary($job->salaryMin, $job->salaryMax, $job->currency),
        );
    }

    public static function date(?DateTimeInterface $date, string $format = 'j M Y'): string
    {
        return $date?->format($format) ?? '';
    }

    public static function dateTime(?DateTimeInterface $date): string
    {
        return $date?->format('j M Y, H:i') ?? '';
    }

    /** Database timestamp string to local display. */
    public static function dbDateTime(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return self::dateTime((new DateTimeImmutable($value))->setTimezone(new \DateTimeZone(date_default_timezone_get())));
    }

    /** "Posted today", "Posted 1 day ago", "Posted 5 days ago". */
    public static function posted(?DateTimeInterface $date, ?DateTimeInterface $now = null): string
    {
        if ($date === null) {
            return '';
        }
        $now ??= new DateTimeImmutable();
        $today = new DateTimeImmutable($now->format('Y-m-d'));
        $day = new DateTimeImmutable($date->format('Y-m-d'));
        $days = max(0, (int) $day->diff($today)->format('%r%a'));

        return match ($days) {
            0 => 'Posted today',
            1 => 'Posted 1 day ago',
            default => "Posted {$days} days ago",
        };
    }
}
