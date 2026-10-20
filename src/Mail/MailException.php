<?php

declare(strict_types=1);

namespace App\Mail;

use RuntimeException;

/** Sending failed. The message is safe to show to the admin (it never contains the password). */
final class MailException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self('Email is not configured yet. Add the SMTP settings in the back office.');
    }
}
