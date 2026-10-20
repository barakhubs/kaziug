<?php

declare(strict_types=1);

namespace App\Mail;

final class MailMessage
{
    /** @param array<string, string> $headers extra headers, e.g. List-Unsubscribe */
    public function __construct(
        public readonly string $toEmail,
        public readonly string $subject,
        public readonly string $html,
        public readonly string $text,
        public readonly ?string $toName = null,
        public readonly array $headers = [],
        /** Overrides the SMTP settings' reply-to, e.g. a visitor's address on contact-form mail. */
        public readonly ?string $replyTo = null,
    ) {
    }
}
