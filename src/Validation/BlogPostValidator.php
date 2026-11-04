<?php

declare(strict_types=1);

namespace App\Validation;

use App\Domain\BlogPostInput;
use App\Validation\Rules\DateTimeRule;

/** Turns the blog post form into a BlogPostInput. */
final class BlogPostValidator
{
    /** Search engines show about this many characters of a meta description. */
    public const META_DESCRIPTION_IDEAL = 155;

    public function __construct(private readonly Validator $validator)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: ?BlogPostInput, 1: array<string, string>}
     */
    public function validate(array $input): array
    {
        foreach (['title', 'excerpt', 'meta_description', 'cover_alt'] as $field) {
            if (isset($input[$field]) && is_string($input[$field])) {
                $input[$field] = trim((string) preg_replace('/\s+/u', ' ', $input[$field]));
            }
        }
        if (isset($input['slug']) && is_string($input['slug'])) {
            $input['slug'] = strtolower(trim($input['slug']));
        }

        $result = $this->validator->validate($input, [
            'title' => 'required|max:200',
            'slug' => 'slug|max:220',
            'excerpt' => 'max:300',
            'meta_description' => 'max:170',
            'body_html' => 'required|max:200000',
            'job_category_id' => 'integer:1',
            'cover_alt' => 'max:200',
            'publish_at' => 'datetime',
        ]);
        $data = $result->data;
        $errors = $result->errors;
        if (isset($errors['body_html']) && in_array($data['body_html'] ?? null, [null, ''], true)) {
            $errors['body_html'] = 'Write the article.';
        }
        if (isset($errors['publish_at'])) {
            $errors['publish_at'] = 'Enter a valid date and time.';
        }

        if ($errors !== []) {
            return [null, $errors];
        }

        return [new BlogPostInput(
            (string) $data['title'],
            $data['slug'] === null ? null : (string) $data['slug'],
            (string) ($data['excerpt'] ?? ''),
            (string) ($data['meta_description'] ?? ''),
            (string) $data['body_html'],
            $data['job_category_id'] === null ? null : (int) $data['job_category_id'],
            (string) ($data['cover_alt'] ?? ''),
            DateTimeRule::parse($data['publish_at'] === null ? null : (string) $data['publish_at']),
        ), []];
    }
}
