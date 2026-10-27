<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

/**
 * An inclusive range of calendar days in the app timezone, chosen on the analytics page either
 * as a preset (?range=30) or as custom dates (?from=2026-01-01&to=2026-01-31).
 */
final class DateRange
{
    public const PRESETS = [7, 30, 90, 365];
    public const DEFAULT_DAYS = 30;
    public const MAX_DAYS = 3 * 366;

    private function __construct(
        public readonly DateTimeImmutable $from,
        public readonly DateTimeImmutable $to,
        public readonly ?int $preset,
    ) {
    }

    /** The last $days days, today included. */
    public static function lastDays(int $days, ?DateTimeImmutable $today = null): self
    {
        $today = self::day($today ?? new DateTimeImmutable());

        return new self($today->modify('-' . (max(1, $days) - 1) . ' days'), $today, in_array($days, self::PRESETS, true) ? $days : null);
    }

    public static function between(DateTimeImmutable $from, DateTimeImmutable $to): self
    {
        [$from, $to] = [self::day($from), self::day($to)];
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diff($to)->days >= self::MAX_DAYS) {
            $from = $to->modify('-' . (self::MAX_DAYS - 1) . ' days');
        }

        return new self($from, $to, null);
    }

    /** @param array<string, mixed> $query raw $_GET; anything invalid falls back to the default */
    public static function fromQuery(array $query, ?DateTimeImmutable $today = null): self
    {
        $from = self::parse($query['from'] ?? null);
        $to = self::parse($query['to'] ?? null);
        if ($from !== null && $to !== null) {
            return self::between($from, $to);
        }
        $days = (int) ($query['range'] ?? self::DEFAULT_DAYS);

        return self::lastDays(in_array($days, self::PRESETS, true) ? $days : self::DEFAULT_DAYS, $today);
    }

    public function days(): int
    {
        return (int) $this->from->diff($this->to)->days + 1;
    }

    /** The range of the same length just before this one, for "compared with the previous period". */
    public function previous(): self
    {
        $to = $this->from->modify('-1 day');

        return new self($to->modify('-' . ($this->days() - 1) . ' days'), $to, null);
    }

    /** @return list<DateTimeImmutable> every day in the range, oldest first */
    public function eachDay(): array
    {
        $days = [];
        for ($day = $this->from; $day <= $this->to; $day = $day->modify('+1 day')) {
            $days[] = $day;
        }

        return $days;
    }

    /** @return array<string, string> query parameters that reproduce this range */
    public function query(): array
    {
        return $this->preset !== null
            ? ['range' => (string) $this->preset]
            : ['from' => $this->from->format('Y-m-d'), 'to' => $this->to->format('Y-m-d')];
    }

    public function label(): string
    {
        return $this->preset !== null
            ? "Last {$this->preset} days"
            : $this->from->format('j M Y') . ' – ' . $this->to->format('j M Y');
    }

    private static function day(DateTimeImmutable $date): DateTimeImmutable
    {
        return $date->setTimezone(new \DateTimeZone(date_default_timezone_get()))->setTime(0, 0);
    }

    private static function parse(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1 || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return new DateTimeImmutable($value);
    }
}
