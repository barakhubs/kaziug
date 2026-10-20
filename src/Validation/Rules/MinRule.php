<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\RuleInterface;

/** Minimum characters for strings. */
final class MinRule implements RuleInterface
{
    public function name(): string
    {
        return 'min';
    }

    public function check(mixed $value, ?string $parameter, array $data): ?string
    {
        $min = (int) $parameter;
        if (!is_string($value)) {
            return 'Invalid value.';
        }

        return mb_strlen($value) < $min ? "Must be at least {$min} characters." : null;
    }
}
