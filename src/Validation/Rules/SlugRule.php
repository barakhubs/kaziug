<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\RuleInterface;

final class SlugRule implements RuleInterface
{
    public function name(): string
    {
        return 'slug';
    }

    public function check(mixed $value, ?string $parameter, array $data): ?string
    {
        return is_string($value) && preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $value) === 1 && strlen($value) <= 120
            ? null
            : 'Use lowercase letters, numbers and single hyphens only.';
    }
}
