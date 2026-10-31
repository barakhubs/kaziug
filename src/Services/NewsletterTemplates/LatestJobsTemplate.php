<?php

declare(strict_types=1);

namespace App\Services\NewsletterTemplates;

use App\Domain\NewsletterDraft;
use App\Domain\SiteSettings;

final class LatestJobsTemplate implements NewsletterTemplateInterface
{
    public function __construct(private readonly JobEmailHtml $html)
    {
    }

    public function key(): string
    {
        return 'latest-jobs';
    }

    public function name(): string
    {
        return 'Latest jobs';
    }

    public function description(): string
    {
        return 'A digest of the newest jobs: title, company, salary and closing date, each linking to the job.';
    }

    public function jobSelection(): string
    {
        return self::JOBS_MANY;
    }

    public function draft(array $jobs, SiteSettings $site, string $appUrl): NewsletterDraft
    {
        $count = count($jobs);
        $titles = array_map(static fn ($job): string => $job->title, array_slice($jobs, 0, 3));
        $preheader = implode(', ', $titles) . ($count > 3 ? ' and more' : '');

        $body = '<p>Hello,</p>'
            . '<p>Here ' . ($count === 1 ? 'is the latest job' : "are the latest {$count} jobs") . ' on ' . e($site->siteName)
            . '. Open a job to read the full details, and apply before the closing date.</p>';
        foreach ($jobs as $job) {
            $body .= '<hr>' . $this->html->listing($job, $appUrl);
        }
        $body .= '<hr>' . $this->html->button($appUrl . '/', 'See all jobs on ' . $site->siteName)
            . '<p>Good luck with your applications!</p>';

        return new NewsletterDraft(
            $count === 1 ? "New job on {$site->siteName}: {$jobs[0]->title}" : "{$count} new jobs on {$site->siteName}",
            mb_substr($preheader, 0, 200),
            $body,
        );
    }
}
