<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\RuleInterface;

/** "same:other_field" */
final class SameRule implements RuleInterface
{
    public function name(): string
    {
        return 'same';
    }

    public function check(mixed $value, ?string $parameter, array $data): ?string
    {
        $other = $data[(string) $parameter] ?? null;

        return is_string($value) && is_string($other) && hash_equals($other, $value) ? null : 'The values do not match.';
    }
}
