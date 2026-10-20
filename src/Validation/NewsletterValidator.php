<?php

declare(strict_types=1);

namespace App\Validation;

final class NewsletterValidator
{
    public function __construct(private readonly Validator $validator)
    {
    }

    /** @param array<string, mixed> $input */
    public function validate(array $input): ValidationResult
    {
        $result = $this->validator->validate($input, [
            'subject' => 'required|max:200',
            'preheader' => 'max:200',
            'body_html' => 'required|max:200000',
            'body_text' => 'max:100000',
        ]);
        if (!isset($result->errors['subject']) && preg_match('/[\r\n]/', (string) $result->data['subject']) === 1) {
            $result = $result->withError('subject', 'The subject must be a single line.');
        }

        return $result;
    }
}
