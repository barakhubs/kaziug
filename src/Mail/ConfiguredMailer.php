<?php

declare(strict_types=1);

namespace App\Mail;

use App\Services\Contracts\SmtpSettingsServiceInterface;

/**
 * The application's MailerInterface: reads the admin's SMTP settings at send time (so changes
 * apply immediately) and delegates to a transport built by the factory.
 */
final class ConfiguredMailer implements MailerInterface
{
    public function __construct(
        private readonly SmtpSettingsServiceInterface $smtp,
        private readonly TransportFactoryInterface $transports,
    ) {
    }

    public function send(MailMessage $message): void
    {
        $config = $this->smtp->config() ?? throw MailException::notConfigured();
        $this->transports->create($config)->send($message);
    }
}
