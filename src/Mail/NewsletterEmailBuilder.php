<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Newsletter;
use App\Services\HtmlSanitizer;

/**
 * Turns a newsletter into the email one subscriber receives. The body is sanitised again here
 * (defence in depth: it was sanitised on save), and the layout adds the physical address and
 * the subscriber's own unsubscribe link and headers.
 */
final class NewsletterEmailBuilder
{
    public function __construct(
        private readonly EmailComposer $composer,
        private readonly HtmlSanitizer $sanitizer,
    ) {
    }

    /** @param string|null $unsubscribeToken null only for previews and test sends */
    public function build(Newsletter $newsletter, string $toEmail, ?string $toName, ?string $unsubscribeToken, string $subjectPrefix = ''): MailMessage
    {
        return $this->composer->compose(
            $toEmail,
            $toName,
            $subjectPrefix . $newsletter->subject,
            $newsletter->preheader,
            $this->sanitizer->sanitize($newsletter->bodyHtml),
            $newsletter->bodyText,
            $unsubscribeToken,
        );
    }

    /** The test/preview version: a sample unsubscribe link so the footer looks exactly as sent. */
    public function buildSample(Newsletter $newsletter, string $toEmail, string $subjectPrefix = ''): MailMessage
    {
        return $this->build($newsletter, $toEmail, null, str_repeat('0', 43), $subjectPrefix);
    }
}
