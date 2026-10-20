<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\RuleInterface;

/** Maximum length in characters. Numeric bounds use "integer:min,max". */
final class MaxRule implements RuleInterface
{
    public function name(): string
    {
        return 'max';
    }

    public function check(mixed $value, ?string $parameter, array $data): ?string
    {
        $max = (int) $parameter;
        if (!is_string($value)) {
            return 'Invalid value.';
        }

        return mb_strlen($value) > $max ? "Must be at most {$max} characters." : null;
    }
}
