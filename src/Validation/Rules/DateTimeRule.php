<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\RuleInterface;
use DateTimeImmutable;

/** Accepts the formats sent by <input type="date"> and <input type="datetime-local">. */
final class DateTimeRule implements RuleInterface
{
    private const FORMATS = ['!Y-m-d\TH:i', '!Y-m-d\TH:i:s', '!Y-m-d'];

    public function name(): string
    {
        return 'datetime';
    }

    public function check(mixed $value, ?string $parameter, array $data): ?string
    {
        return is_string($value) && self::parse($value) !== null ? null : 'Enter a valid date.';
    }

    /** Parsed in the app timezone (APP_TIMEZONE). */
    public static function parse(?string $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        foreach (self::FORMATS as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date;
            }
        }

        return null;
    }
}
