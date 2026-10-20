<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

/** SMTP settings as the back office sees them: the password is never included, only whether one is saved. */
final class SmtpSettings
{
    public const ENCRYPTIONS = ['tls' => 'STARTTLS (usually port 587)', 'ssl' => 'SSL/TLS (usually port 465)', 'none' => 'None (not recommended)'];

    public function __construct(
        public readonly string $host = '',
        public readonly int $port = 587,
        public readonly string $encryption = 'tls',
        public readonly string $username = '',
        public readonly bool $hasPassword = false,
        public readonly string $fromName = '',
        public readonly string $fromEmail = '',
        public readonly string $replyTo = '',
        public readonly ?DateTimeImmutable $lastTestedAt = null,
        public readonly ?string $lastTestResult = null,
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->host !== '' && $this->fromEmail !== '';
    }

    public function lastTestSucceeded(): ?bool
    {
        return $this->lastTestResult === null ? null : str_starts_with($this->lastTestResult, 'OK');
    }
}
