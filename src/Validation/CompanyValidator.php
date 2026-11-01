<?php

declare(strict_types=1);

namespace App\Validation;

final class CompanyValidator
{
    public function __construct(private readonly Validator $validator)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return ValidationResult data: name (single-spaced), website (null when empty)
     */
    public function validate(array $input): ValidationResult
    {
        // Single spaces keep "Acme  Ltd" and "Acme Ltd" from becoming two companies.
        if (isset($input['name']) && is_string($input['name'])) {
            $input['name'] = (string) preg_replace('/\s+/u', ' ', trim($input['name']));
        }
        if (isset($input['website']) && is_string($input['website'])) {
            $input['website'] = trim($input['website']);
        }

        return $this->validator->validate($input, [
            'name' => 'required|max:200',
            'website' => 'url|max:2048',
        ]);
    }
}
