<?php

declare(strict_types=1);

namespace App\Validation;

use App\Domain\ExperienceLevel;
use App\Domain\JobInput;
use App\Domain\JobType;
use App\Validation\Rules\DateTimeRule;
use App\Validation\Rules\IntegerRule;

/**
 * Turns the job form into a JobInput. Lists come from textareas (one item per line)
 * and skills from a comma-separated field, as in the prototype's form.
 */
final class JobValidator
{
    private const MAX_LIST_ITEMS = 30;
    private const MAX_SKILLS = 20;

    public function __construct(private readonly Validator $validator)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @return array{0: ?JobInput, 1: array<string, string>}
     */
    public function validate(array $input): array
    {
        $result = $this->validator->validate($input, [
            'title' => 'required|max:200',
            'company' => 'required|max:200',
            'location' => 'required|max:120',
            'is_remote' => 'boolean',
            'job_type' => 'required|in:' . implode(',', JobType::values()),
            'experience_level' => 'required|in:' . implode(',', ExperienceLevel::values()),
            'category_id' => 'required|integer:1',
            'salary_min' => 'integer:0,1000000000000',
            'salary_max' => 'integer:0,1000000000000',
            'currency' => 'max:3',
            'summary' => 'required|max:2000',
            'responsibilities' => 'max:10000',
            'requirements' => 'max:10000',
            'skills' => 'max:2000',
            'apply_url' => 'url',
            'apply_email' => 'email',
            'expires_at' => 'datetime',
        ]);
        $data = $result->data;
        $errors = $result->errors;

        $salaryMin = isset($errors['salary_min']) ? null : IntegerRule::toInt($data['salary_min']);
        $salaryMax = isset($errors['salary_max']) ? null : IntegerRule::toInt($data['salary_max']);
        if ($salaryMin !== null && $salaryMax !== null && $salaryMax < $salaryMin) {
            $errors['salary_max'] ??= 'Must be at least the minimum salary.';
        }

        $currency = strtoupper((string) ($data['currency'] ?? '')) ?: 'UGX';
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            $errors['currency'] ??= 'Use a three-letter currency code, e.g. UGX.';
        }

        $responsibilities = self::lines((string) ($data['responsibilities'] ?? ''));
        $requirements = self::lines((string) ($data['requirements'] ?? ''));
        $skills = self::commaList((string) ($data['skills'] ?? ''));
        foreach (['responsibilities' => $responsibilities, 'requirements' => $requirements] as $field => $list) {
            if (count($list) > self::MAX_LIST_ITEMS) {
                $errors[$field] ??= 'Use at most ' . self::MAX_LIST_ITEMS . ' lines.';
            }
        }
        if (count($skills) > self::MAX_SKILLS || array_filter($skills, static fn (string $s): bool => mb_strlen($s) > 60) !== []) {
            $errors['skills'] ??= 'Use at most ' . self::MAX_SKILLS . ' skills of up to 60 characters each.';
        }

        if ($errors !== []) {
            return [null, $errors];
        }

        return [new JobInput(
            (string) $data['title'],
            (string) $data['company'],
            (string) $data['location'],
            (bool) $data['is_remote'],
            JobType::from((string) $data['job_type']),
            ExperienceLevel::from((string) $data['experience_level']),
            (int) $data['category_id'],
            $salaryMin,
            $salaryMax,
            $currency,
            (string) $data['summary'],
            $responsibilities,
            $requirements,
            $skills,
            $data['apply_url'],
            $data['apply_email'] === null ? null : strtolower((string) $data['apply_email']),
            DateTimeRule::parse($data['expires_at']),
        ), []];
    }

    /** @return list<string> */
    public static function lines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $text) ?: []), static fn (string $l): bool => $l !== ''));
    }

    /** @return list<string> unique, order kept */
    public static function commaList(string $text): array
    {
        $items = array_filter(array_map('trim', explode(',', $text)), static fn (string $s): bool => $s !== '');

        return array_values(array_unique($items));
    }
}
