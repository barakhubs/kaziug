<?php

declare(strict_types=1);

namespace App\Services\NewsletterTemplates;

use App\Core\Format;
use App\Domain\NewsletterDraft;
use App\Domain\SiteSettings;

final class JobSpotlightTemplate implements NewsletterTemplateInterface
{
    public function __construct(private readonly JobEmailHtml $html)
    {
    }

    public function key(): string
    {
        return 'job-spotlight';
    }

    public function name(): string
    {
        return 'Job spotlight';
    }

    public function description(): string
    {
        return 'One job in detail: summary, responsibilities, requirements and how to apply.';
    }

    public function jobSelection(): string
    {
        return self::JOBS_ONE;
    }

    public function draft(array $jobs, SiteSettings $site, string $appUrl): NewsletterDraft
    {
        $job = $jobs[0];
        $facts = [e(Format::jobSalary($job))];
        if ($job->positions > 1) {
            $facts[] = e(Format::positions($job->positions));
        }
        if ($job->expiresAt !== null) {
            $facts[] = 'Apply by <strong>' . e(Format::date($job->expiresAt, 'l, j F Y')) . '</strong>';
        }

        $body = '<h2>' . e($job->title) . '</h2>'
            . '<p><strong>' . e($job->company) . '</strong> · ' . e($this->html->place($job)) . '<br>' . implode('<br>', $facts) . '</p>'
            . '<p>' . e($job->summary) . '</p>';
        if ($job->responsibilities !== []) {
            $body .= '<h3>What you will do</h3>' . $this->html->bulletList($job->responsibilities);
        }
        if ($job->requirements !== []) {
            $body .= '<h3>What you need</h3>' . $this->html->bulletList($job->requirements);
        }
        if ($job->applicationInstructions !== null) {
            $body .= '<h3>How to apply</h3><p>' . nl2br(e($job->applicationInstructions), false) . '</p>';
        }
        $body .= $this->html->button($this->html->url($job, $appUrl), 'View the job and apply');

        return new NewsletterDraft(
            "Job opening: {$job->title} at {$job->company}",
            mb_substr($job->company . ' is hiring in ' . $job->location . '. ' . Format::excerpt($job->summary, 120), 0, 200),
            $body,
        );
    }
}
