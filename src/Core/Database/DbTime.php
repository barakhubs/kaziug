<?php

declare(strict_types=1);

namespace App\Core\Database;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * DATETIME columns hold UTC wall-clock time with no offset (MariaDB rejects offsets in literals),
 * so every value crossing the database boundary goes through here.
 */
final class DbTime
{
    private const FORMAT = 'Y-m-d H:i:s.u';

    public static function toDb(?DateTimeInterface $date): ?string
    {
        return $date === null
            ? null
            : DateTimeImmutable::createFromInterface($date)->setTimezone(new DateTimeZone('UTC'))->format(self::FORMAT);
    }

    /** Reads a DATETIME value as UTC and returns it in the app's timezone. */
    public static function fromDb(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || $value === '') {
            return null;
        }

        return (new DateTimeImmutable($value, new DateTimeZone('UTC')))
            ->setTimezone(new DateTimeZone(date_default_timezone_get()));
    }
}
