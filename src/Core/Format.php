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

    /** A job's pay as candidates see it: an amount or a public-service salary scale. */
    public static function jobSalary(\App\Domain\Job $job): string
    {
        return $job->salaryScale !== null
            ? 'Salary scale ' . $job->salaryScale
            : self::salary($job->salaryMin, $job->salaryMax, $job->currency);
    }

    /** "3 positions", or '' for the usual single opening. */
    public static function positions(int $positions): string
    {
        return $positions > 1 ? number_format($positions) . ' positions' : '';
    }

    /**
     * How an application deadline reads to a job seeker, by calendar day in the app timezone.
     * @return array{text: string, state: 'closed'|'today'|'soon'|'open'} state drives the styling
     */
    public static function deadline(DateTimeInterface $deadline, ?DateTimeInterface $now = null): array
    {
        $now ??= new DateTimeImmutable();
        if ($deadline <= $now) {
            return ['text' => 'Applications closed ' . self::date($deadline), 'state' => 'closed'];
        }
        $today = new DateTimeImmutable($now->format('Y-m-d'));
        $day = new DateTimeImmutable($deadline->format('Y-m-d'));
        $days = (int) $today->diff($day)->format('%r%a');

        return match (true) {
            $days <= 0 => ['text' => 'Closes today', 'state' => 'today'],
            $days === 1 => ['text' => 'Closes tomorrow', 'state' => 'soon'],
            $days <= 3 => ['text' => "Closes in {$days} days", 'state' => 'soon'],
            $days <= 14 => ['text' => "Closes in {$days} days", 'state' => 'open'],
            default => ['text' => 'Apply by ' . self::date($deadline), 'state' => 'open'],
        };
    }

    /**
     * The deadline line for places that already show the full date: like deadline(), but far-off
     * dates read "18 days left" instead of repeating the date.
     */
    public static function deadlineCountdown(DateTimeInterface $deadline, ?DateTimeInterface $now = null): string
    {
        $now ??= new DateTimeImmutable();
        $label = self::deadline($deadline, $now);
        if (!str_starts_with($label['text'], 'Apply by')) {
            return $label['text'];
        }
        $days = (int) (new DateTimeImmutable($now->format('Y-m-d')))->diff(new DateTimeImmutable($deadline->format('Y-m-d')))->format('%a');

        return "{$days} days left";
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

        $pay = $job->hasSalaryAmount() || $job->salaryScale !== null ? self::jobSalary($job) : 'not disclosed';
        $openings = $job->positions > 1 ? ' ' . self::positions($job->positions) . ' available.' : '';

        return sprintf(
            '%s %s-level %s role at %s in %s. Salary: %s.%s',
            $job->jobType->label(),
            strtolower($job->experienceLevel->label()),
            $job->categoryName,
            $job->company,
            $where,
            $pay,
            $openings,
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
