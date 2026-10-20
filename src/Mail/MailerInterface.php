<?php

declare(strict_types=1);

namespace App\Mail;

/**
 * Sends one email. Services depend on this, never on PHPMailer, so the transport can be
 * replaced (or faked in tests) without touching them.
 */
interface MailerInterface
{
    /** @throws MailException when the message could not be handed to the mail server */
    public function send(MailMessage $message): void;
}
