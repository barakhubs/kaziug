<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\RuleInterface;

/** "in:a,b,c" */
final class InRule implements RuleInterface
{
    public function name(): string
    {
        return 'in';
    }

    public function check(mixed $value, ?string $parameter, array $data): ?string
    {
        $allowed = explode(',', (string) $parameter);

        return is_string($value) && in_array($value, $allowed, true) ? null : 'Choose one of the listed options.';
    }
}
