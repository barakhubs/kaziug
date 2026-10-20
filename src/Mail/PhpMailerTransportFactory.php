<?php

declare(strict_types=1);

namespace App\Mail;

final class PhpMailerTransportFactory implements TransportFactoryInterface
{
    public function create(SmtpConfig $config): MailerInterface
    {
        return new PhpMailerTransport($config);
    }
}
