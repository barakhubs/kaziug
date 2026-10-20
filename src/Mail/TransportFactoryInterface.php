<?php

declare(strict_types=1);

namespace App\Mail;

/** Builds a mailer for a given SMTP configuration. */
interface TransportFactoryInterface
{
    public function create(SmtpConfig $config): MailerInterface;
}
