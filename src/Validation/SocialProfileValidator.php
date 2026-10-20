<?php

declare(strict_types=1);

namespace App\Validation;

use App\Domain\SocialIcon;

final class SocialProfileValidator
{
    public function __construct(private readonly Validator $validator)
    {
    }

    /** @param array<string, mixed> $input */
    public function validate(array $input): ValidationResult
    {
        return $this->validator->validate($input, [
            'platform' => 'required|max:60',
            'label' => 'required|max:100',
            'url' => 'required|url',
            'icon_key' => 'required|in:' . implode(',', SocialIcon::values()),
            'is_visible' => 'boolean',
        ]);
    }
}
