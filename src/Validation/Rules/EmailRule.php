<?php

declare(strict_types=1);

namespace App\Validation\Rules;

use App\Validation\RuleInterface;

final class EmailRule implements RuleInterface
{
    public function name(): string
    {
        return 'email';
    }

    public function check(mixed $value, ?string $parameter, array $data): ?string
    {
        // filter_var also rejects CR/LF, which keeps addresses safe to use in mail headers.
        if (!is_string($value) || strlen($value) > 254 || filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            return 'Enter a valid email address.';
        }

        return null;
    }
}
