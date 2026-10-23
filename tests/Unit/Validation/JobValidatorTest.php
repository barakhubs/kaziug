<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use App\Core\Format;
use App\Domain\JobType;
use App\Domain\Slug;
use App\Validation\JobValidator;
use App\Validation\Validator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class JobValidatorTest extends TestCase
{
    private JobValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new JobValidator(new Validator());
    }

    public function testValidFormBecomesJobInput(): void
    {
        [$input, $errors] = $this->validator->validate($this->form([
            'responsibilities' => "Build components\r\n\r\n  Review code  \n",
            'skills' => 'React, CSS,  ,React',
            'salary_min' => '3,500,000',
            'is_remote' => '1',
            'currency' => 'ugx',
        ]));

        $this->assertSame([], $errors);
        $this->assertSame(['Build components', 'Review code'], $input?->responsibilities);
        $this->assertSame(['React', 'CSS'], $input?->skills);
        $this->assertSame(3_500_000, $input?->salaryMin);
        $this->assertSame('UGX', $input?->currency);
        $this->assertTrue($input?->isRemote);
        $this->assertSame(JobType::Contract, $input?->jobType);
    }

    public function testRejectsBadValues(): void
    {
        [$input, $errors] = $this->validator->validate($this->form([
            'title' => '',
            'job_type' => 'gig',
            'salary_min' => '500',
            'salary_max' => '100',
            'apply_url' => 'javascript:alert(1)',
            'apply_email' => 'nope',
            'currency' => 'US',
        ]));

        $this->assertNull($input);
        $this->assertSame(['title', 'job_type', 'apply_url', 'apply_email', 'salary_max', 'currency'], array_keys($errors));
    }

    public function testApplicationDetailsAreRequiredForTheChosenMethod(): void
    {
        [, $errors] = $this->validator->validate($this->form(['apply_method' => '']));
        $this->assertSame('Choose how candidates apply.', $errors['apply_method']);

        [, $errors] = $this->validator->validate($this->form(['apply_method' => 'link', 'apply_url' => '']));
        $this->assertArrayHasKey('apply_url', $errors);

        [, $errors] = $this->validator->validate($this->form(['apply_method' => 'both', 'apply_email' => '']));
        $this->assertArrayHasKey('apply_email', $errors);

        [$linkOnly] = $this->validator->validate($this->form(['apply_method' => 'link']));
        $this->assertSame('https://savanna.example/careers/42', $linkOnly?->applyUrl);
        $this->assertNull($linkOnly?->applyEmail, 'the unchosen field is cleared');

        [$emailOnly] = $this->validator->validate($this->form(['apply_method' => 'email', 'apply_url' => '']));
        $this->assertSame('jobs@savanna.example', $emailOnly?->applyEmail);
        $this->assertNull($emailOnly?->applyUrl);

        $this->assertSame('both', JobValidator::applyMethodFor('https://x.ug', 'a@x.ug'));
        $this->assertSame('', JobValidator::applyMethodFor(null, null));
    }

    public function testDeadlineIsRequiredNotPastAndLastsTheWholeDay(): void
    {
        [, $errors] = $this->validator->validate($this->form(['expires_at' => '']));
        $this->assertSame('Set the application deadline.', $errors['expires_at']);

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        [, $errors] = $this->validator->validate($this->form(['expires_at' => $yesterday]));
        $this->assertSame('The deadline can’t be in the past.', $errors['expires_at']);

        // Editing a job whose saved deadline has already passed may keep that date.
        [$kept] = $this->validator->validate($this->form(['expires_at' => $yesterday]), new DateTimeImmutable($yesterday . ' 23:59:59'));
        $this->assertNotNull($kept);

        [$today] = $this->validator->validate($this->form(['expires_at' => date('Y-m-d')]));
        $this->assertSame(date('Y-m-d') . ' 23:59:59', $today?->expiresAt?->format('Y-m-d H:i:s'));
    }

    public function testDeadlineWording(): void
    {
        $now = new DateTimeImmutable('2026-10-20 10:00');
        $at = static fn (string $d): DateTimeImmutable => new DateTimeImmutable($d . ' 23:59:59');

        $this->assertSame(['text' => 'Closes today', 'state' => 'today'], Format::deadline($at('2026-10-20'), $now));
        $this->assertSame(['text' => 'Closes tomorrow', 'state' => 'soon'], Format::deadline($at('2026-10-21'), $now));
        $this->assertSame(['text' => 'Closes in 3 days', 'state' => 'soon'], Format::deadline($at('2026-10-23'), $now));
        $this->assertSame(['text' => 'Closes in 10 days', 'state' => 'open'], Format::deadline($at('2026-10-30'), $now));
        $this->assertSame(['text' => 'Apply by 30 Nov 2026', 'state' => 'open'], Format::deadline($at('2026-11-30'), $now));
        $this->assertSame('closed', Format::deadline($at('2026-10-19'), $now)['state']);
    }

    public function testFormattingHelpers(): void
    {
        $this->assertSame('frontend-developer-react', Slug::from('Frontend Developer (React)'));
        $this->assertSame('UGX 3.5M – UGX 5M / month', Format::salary(3_500_000, 5_000_000, 'UGX'));
        $this->assertSame('UGX 800,000 / month', Format::salary(800_000, 800_000, 'UGX'));
        $this->assertSame('Salary not disclosed', Format::salary(null, null, 'UGX'));
        $now = new DateTimeImmutable('2026-10-20 12:00');
        $this->assertSame('Posted today', Format::posted(new DateTimeImmutable('2026-10-20 01:00'), $now));
        $this->assertSame('Posted 1 day ago', Format::posted(new DateTimeImmutable('2026-10-19 23:00'), $now));
        $this->assertSame('Posted 6 days ago', Format::posted(new DateTimeImmutable('2026-10-14'), $now));
    }

    /**
     * @param array<string, string> $overrides
     * @return array<string, string>
     */
    private function form(array $overrides): array
    {
        return [...[
            'title' => 'Customer Support Agent',
            'company' => 'Savanna Tech Labs',
            'location' => 'Kampala',
            'job_type' => 'contract',
            'experience_level' => 'entry',
            'category_id' => '1',
            'summary' => 'Help customers.',
            'apply_method' => 'both',
            'apply_url' => 'https://savanna.example/careers/42',
            'apply_email' => 'Jobs@Savanna.example',
            'expires_at' => date('Y-m-d', strtotime('+14 days')),
        ], ...$overrides];
    }
}
