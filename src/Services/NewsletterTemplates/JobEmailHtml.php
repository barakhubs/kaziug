<?php

declare(strict_types=1);

namespace App\Services\NewsletterTemplates;

use App\Core\Format;
use App\Domain\Job;

/** Shared, escaped HTML fragments for jobs in newsletter templates. */
final class JobEmailHtml
{
    public function url(Job $job, string $appUrl): string
    {
        return $appUrl . '/jobs/' . rawurlencode($job->slug);
    }

    /** One job in a list: linked title, company and place, salary and the closing date. */
    public function listing(Job $job, string $appUrl): string
    {
        $url = e($this->url($job, $appUrl));
        $lines = ['<strong>' . e($job->company) . '</strong> · ' . e($this->place($job))];
        $lines[] = e(Format::jobSalary($job));
        if ($job->expiresAt !== null) {
            $lines[] = 'Apply by ' . e(Format::date($job->expiresAt, 'j M Y'));
        }
        $lines[] = "<a href=\"{$url}\">View and apply →</a>";

        return "<h3><a href=\"{$url}\">" . e($job->title) . '</a></h3>'
            . '<p>' . implode('<br>', $lines) . '</p>';
    }

    public function place(Job $job): string
    {
        return $job->location . ($job->isRemote ? ' (remote-friendly)' : '');
    }

    /** @param list<string> $items */
    public function bulletList(array $items): string
    {
        $items = array_values(array_filter(array_map('trim', $items), static fn (string $i): bool => $i !== ''));

        return $items === [] ? '' : '<ul>' . implode('', array_map(static fn (string $i): string => '<li>' . e($i) . '</li>', $items)) . '</ul>';
    }

    /** A paragraph holding a single link; the email layout shows it as a button. */
    public function button(string $url, string $label): string
    {
        return '<p><a href="' . e($url) . '">' . e($label) . '</a></p>';
    }
}
