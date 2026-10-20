<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\RuleInterface;

/** Absolute http(s) URL only, so stored links can never be javascript: or data: URLs. */
final class UrlRule implements RuleInterface
{
    public function name(): string
    {
        return 'url';
    }

    public function check(mixed $value, ?string $parameter, array $data): ?string
    {
        if (!is_string($value) || strlen($value) > 2000
            || preg_match('#^https?://#i', $value) !== 1
            || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return 'Enter a full web address starting with https://';
        }

        return null;
    }
}
