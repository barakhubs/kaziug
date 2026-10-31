<?php

declare(strict_types=1);

namespace App\Services\NewsletterTemplates;

use App\Core\Format;
use App\Domain\NewsletterDraft;
use App\Domain\SiteSettings;

final class CvHelpTemplate implements NewsletterTemplateInterface
{
    public function __construct(private readonly JobEmailHtml $html)
    {
    }

    public function key(): string
    {
        return 'cv-help';
    }

    public function name(): string
    {
        return 'CV help promo';
    }

    public function description(): string
    {
        return 'Promotes the CV writing service with the price and WhatsApp number from Settings.';
    }

    public function jobSelection(): string
    {
        return self::JOBS_NONE;
    }

    public function draft(array $jobs, SiteSettings $site, string $appUrl): NewsletterDraft
    {
        $price = Format::money($site->cvServicePrice);
        $body = '<h2>Is your CV holding you back?</h2>'
            . '<p>Employers spend seconds on each CV. Our team will rewrite yours so it is clear, professional and matched to the jobs you want, for <strong>' . e($price) . '</strong>.</p>'
            . $this->html->bulletList([
                'A clean, modern layout that is easy to read',
                'Your experience and skills written to stand out',
                'Tailored to the kind of jobs you are applying for',
            ])
            . $this->html->button($appUrl . '/cv-help', 'Get help with my CV');
        $whatsapp = $site->whatsappLink("Hello {$site->siteName}, I'd like help with my CV.");
        if ($whatsapp !== null) {
            $body .= '<p>Prefer to chat? Message us on WhatsApp: <a href="' . e($whatsapp) . '">' . e($site->whatsappDisplay()) . '</a></p>';
        }

        return new NewsletterDraft(
            'Get your CV ready for your next job',
            "Professional CV writing from {$site->siteName} for {$price}",
            $body,
        );
    }
}
