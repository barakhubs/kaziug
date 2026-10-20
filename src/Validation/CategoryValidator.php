<?php

declare(strict_types=1);

namespace App\Validation;

final class CategoryValidator
{
    public function __construct(private readonly Validator $validator)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return ValidationResult data: name, slug (null = derive from name), description, is_active
     */
    public function validate(array $input): ValidationResult
    {
        if (isset($input['slug']) && is_string($input['slug'])) {
            $input['slug'] = strtolower(trim($input['slug']));
        }

        return $this->validator->validate($input, [
            'name' => 'required|max:100',
            'slug' => 'slug',
            'description' => 'max:1000',
            'is_active' => 'boolean',
        ]);
    }
}
