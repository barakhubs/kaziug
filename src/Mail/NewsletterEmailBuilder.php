<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Newsletter;
use App\Services\HtmlSanitizer;

/**
 * Turns a newsletter into the email one subscriber receives. The body is sanitised again here
 * (defence in depth: it was sanitised on save), then styled inline, and the layout adds the
 * physical address and the subscriber's own unsubscribe link and headers.
 */
final class NewsletterEmailBuilder
{
    private const SAMPLE_TOKEN = '0000000000000000000000000000000000000000000';

    public function __construct(
        private readonly EmailComposer $composer,
        private readonly HtmlSanitizer $sanitizer,
        private readonly EmailStyler $styler,
    ) {
    }

    /** @param string|null $unsubscribeToken null only for previews and test sends */
    public function build(Newsletter $newsletter, string $toEmail, ?string $toName, ?string $unsubscribeToken, string $subjectPrefix = ''): MailMessage
    {
        return $this->compose($subjectPrefix . $newsletter->subject, $newsletter->preheader, $newsletter->bodyHtml, $newsletter->bodyText, $toEmail, $toName, $unsubscribeToken);
    }

    /** The test/preview version: a sample unsubscribe link so the footer looks exactly as sent. */
    public function buildSample(Newsletter $newsletter, string $toEmail, string $subjectPrefix = ''): MailMessage
    {
        return $this->build($newsletter, $toEmail, null, self::SAMPLE_TOKEN, $subjectPrefix);
    }

    /** Preview of unsaved editor content, exactly as a subscriber would see it. */
    public function buildDraftSample(string $subject, string $preheader, string $bodyHtml): MailMessage
    {
        return $this->compose($subject, $preheader, $bodyHtml, '', 'preview@example.com', null, self::SAMPLE_TOKEN);
    }

    private function compose(string $subject, string $preheader, string $bodyHtml, string $bodyText, string $toEmail, ?string $toName, ?string $unsubscribeToken): MailMessage
    {
        return $this->composer->compose(
            $toEmail,
            $toName,
            $subject,
            $preheader,
            $this->styler->style($this->sanitizer->sanitize($bodyHtml)),
            $bodyText,
            $unsubscribeToken,
        );
    }
}
