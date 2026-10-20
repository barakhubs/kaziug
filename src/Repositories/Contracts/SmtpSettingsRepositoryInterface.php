<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface SmtpSettingsRepositoryInterface
{
    /** @return array<string, mixed>|null the single row, including password_encrypted */
    public function find(): ?array;

    /**
     * @param array{host: string, port: int, encryption: string, username: string, from_name: string, from_email: string, reply_to: string} $fields
     * @param string|null|false $passwordEncrypted new ciphertext; null clears it; false keeps the stored one
     */
    public function save(array $fields, string|null|false $passwordEncrypted): void;

    public function recordTest(string $result): void;
}
