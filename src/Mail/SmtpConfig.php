<?php

declare(strict_types=1);

namespace App\Mail;

/** Decrypted SMTP settings, built just before sending and never stored or logged. */
final class SmtpConfig
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $encryption,
        public readonly string $username,
        #[\SensitiveParameter] public readonly string $password,
        public readonly string $fromEmail,
        public readonly string $fromName,
        public readonly string $replyTo,
        public readonly int $timeout,
    ) {
    }

    public function __debugInfo(): array
    {
        return ['host' => $this->host, 'port' => $this->port, 'encryption' => $this->encryption, 'password' => '[hidden]'];
    }
}
