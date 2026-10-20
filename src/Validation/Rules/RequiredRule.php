<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\RuleInterface;

final class RequiredRule implements RuleInterface
{
    public function name(): string
    {
        return 'required';
    }

    public function check(mixed $value, ?string $parameter, array $data): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return 'This field is required.';
        }
        if (is_array($value)) {
            return 'Invalid value.';
        }

        return null;
    }
}
