<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\Actor;
use App\Domain\SmtpSettings;
use App\Mail\SmtpConfig;

interface SmtpSettingsServiceInterface
{
    public function settings(): SmtpSettings;

    /**
     * @param array{host: string, port: int, encryption: string, username: string, from_name: string, from_email: string, reply_to: string} $fields
     * @param string|null $newPassword null or '' keeps the saved password
     */
    public function update(array $fields, #[\SensitiveParameter] ?string $newPassword, bool $clearPassword, Actor $actor): void;

    /**
     * Sends a test email right away and records the outcome.
     * @return array{ok: bool, message: string}
     */
    public function sendTest(string $to, Actor $actor): array;

    /** Decrypted settings for sending, or null when mail is not configured. */
    public function config(): ?SmtpConfig;
}
