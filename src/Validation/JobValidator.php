<?php

declare(strict_types=1);

namespace App\Validation;

use App\Domain\ExperienceLevel;
use App\Domain\JobInput;
use App\Domain\JobType;
use App\Validation\Rules\DateTimeRule;
use App\Validation\Rules\IntegerRule;
use DateTimeImmutable;

/**
 * Turns the job form into a JobInput. Lists come from textareas (one item per line)
 * and skills from a comma-separated field, as in the prototype's form.
 */
final class JobValidator
{
    /** How candidates apply: through a link (application page / more info), by email, or both. */
    public const APPLY_METHODS = ['link', 'email', 'both'];
    private const MAX_LIST_ITEMS = 30;
    private const MAX_SKILLS = 20;

    public function __construct(private readonly Validator $validator)
    {
    }

    /**
     * @param array<string, mixed> $input
     * @param DateTimeImmutable|null $currentDeadline the saved deadline when editing; it may be kept even if it has passed
     * @return array{0: ?JobInput, 1: array<string, string>}
     */
    public function validate(array $input, ?DateTimeImmutable $currentDeadline = null): array
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
            'apply_method' => 'required|in:' . implode(',', self::APPLY_METHODS),
            'apply_url' => 'url',
            'apply_email' => 'email',
            'expires_at' => 'required|datetime',
        ]);
        $data = $result->data;
        $errors = $result->errors;

        // The deadline is a calendar day: applications stay open until the end of it.
        $deadline = isset($errors['expires_at']) ? null : DateTimeRule::parse($data['expires_at'])?->setTime(23, 59, 59);
        if (isset($errors['expires_at'])) {
            $errors['expires_at'] = in_array($data['expires_at'], [null, ''], true) ? 'Set the application deadline.' : 'Enter a valid date.';
        } elseif ($deadline !== null && $deadline < new DateTimeImmutable('today')
            && $deadline->format('Y-m-d') !== $currentDeadline?->format('Y-m-d')) {
            $errors['expires_at'] = 'The deadline can’t be in the past.';
        }

        $salaryMin = isset($errors['salary_min']) ? null : IntegerRule::toInt($data['salary_min']);
        $salaryMax = isset($errors['salary_max']) ? null : IntegerRule::toInt($data['salary_max']);
        if ($salaryMin !== null && $salaryMax !== null && $salaryMax < $salaryMin) {
            $errors['salary_max'] ??= 'Must be at least the minimum salary.';
        }

        // The chosen method decides which contact fields are required; the other one is cleared.
        $method = (string) ($data['apply_method'] ?? '');
        $needsLink = $method === 'link' || $method === 'both';
        $needsEmail = $method === 'email' || $method === 'both';
        if (!isset($errors['apply_method'])) {
            if ($needsLink && $data['apply_url'] === null) {
                $errors['apply_url'] ??= 'Enter the link where candidates apply or read more.';
            }
            if ($needsEmail && $data['apply_email'] === null) {
                $errors['apply_email'] ??= 'Enter the email address applications go to.';
            }
        } else {
            $errors['apply_method'] = 'Choose how candidates apply.';
        }
        $applyUrl = $needsLink ? $data['apply_url'] : null;
        $applyEmail = $needsEmail && $data['apply_email'] !== null ? strtolower((string) $data['apply_email']) : null;

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
            $applyUrl,
            $applyEmail,
            $deadline,
        ), []];
    }

    /** The method matching a job's stored contact fields ('' when it has none yet). */
    public static function applyMethodFor(?string $url, ?string $email): string
    {
        return match (true) {
            $url !== null && $email !== null => 'both',
            $url !== null => 'link',
            $email !== null => 'email',
            default => '',
        };
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
