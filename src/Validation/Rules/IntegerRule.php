<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\RuleInterface;

/** Whole number, optionally bounded: "integer", "integer:0", "integer:1,100". */
final class IntegerRule implements RuleInterface
{
    public function name(): string
    {
        return 'integer';
    }

    public function check(mixed $value, ?string $parameter, array $data): ?string
    {
        if (is_string($value)) {
            $value = str_replace([',', ' ', '_'], '', $value);
        }
        if (!is_int($value) && !(is_string($value) && preg_match('/^-?\d{1,18}$/', $value) === 1)) {
            return 'Enter a whole number.';
        }

        $number = (int) $value;
        if ($parameter !== null) {
            [$min, $max] = array_pad(explode(',', $parameter, 2), 2, null);
            if ($min !== null && $min !== '' && $number < (int) $min) {
                return "Must be at least {$min}.";
            }
            if ($max !== null && $number > (int) $max) {
                return "Must be at most {$max}.";
            }
        }

        return null;
    }

    /** Normalises a value that passed this rule ("3,500,000" -> 3500000). */
    public static function toInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) str_replace([',', ' ', '_'], '', (string) $value);
    }
}
