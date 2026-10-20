<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\RuleInterface;

/** Checkbox semantics: a missing field is false. */
final class BooleanRule implements RuleInterface
{
    public function name(): string
    {
        return 'boolean';
    }

    public function check(mixed $value, ?string $parameter, array $data): ?string
    {
        return $value === null || is_bool($value) || in_array($value, ['', '0', '1', 'on', 'true', 'false'], true)
            ? null
            : 'Invalid value.';
    }

    public static function toBool(mixed $value): bool
    {
        return in_array($value, [true, '1', 'on', 'true'], true);
    }
}
