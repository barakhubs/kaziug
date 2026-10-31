<?php

declare(strict_types=1);

namespace App\Services\NewsletterTemplates;

use App\Domain\NewsletterDraft;
use App\Domain\SiteSettings;

final class AnnouncementTemplate implements NewsletterTemplateInterface
{
    public function __construct(private readonly JobEmailHtml $html)
    {
    }

    public function key(): string
    {
        return 'announcement';
    }

    public function name(): string
    {
        return 'Announcement';
    }

    public function description(): string
    {
        return 'A heading, two short paragraphs and a button. Replace the placeholder text with your news.';
    }

    public function jobSelection(): string
    {
        return self::JOBS_NONE;
    }

    public function draft(array $jobs, SiteSettings $site, string $appUrl): NewsletterDraft
    {
        $name = e($site->siteName);
        $body = '<h2>Your headline here</h2>'
            . '<p>Hello,</p>'
            . '<p>Write your news here: what is new, what changed, or what is coming up on ' . $name . '.</p>'
            . '<p>Add a second paragraph with any details or dates people need to know.</p>'
            . $this->html->button($appUrl . '/', 'Visit ' . $site->siteName)
            . '<p>Thank you for being part of ' . $name . '.</p>';

        return new NewsletterDraft("News from {$site->siteName}", "A short update from {$site->siteName}", $body);
    }
}
