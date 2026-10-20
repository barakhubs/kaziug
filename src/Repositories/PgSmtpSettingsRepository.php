<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Repositories\Contracts\SmtpSettingsRepositoryInterface;

final class PgSmtpSettingsRepository implements SmtpSettingsRepositoryInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    public function find(): ?array
    {
        return $this->db->fetchOne('SELECT * FROM smtp_settings WHERE id = 1');
    }

    public function save(array $fields, string|null|false $passwordEncrypted): void
    {
        $keep = $passwordEncrypted === false;
        $this->db->execute(
            'INSERT INTO smtp_settings (id, host, port, encryption, username, from_name, from_email, reply_to, password_encrypted)
             VALUES (1, :host, :port, :encryption, :username, :from_name, :from_email, :reply_to, :password)
             ON CONFLICT (id) DO UPDATE SET host = EXCLUDED.host, port = EXCLUDED.port, encryption = EXCLUDED.encryption,
                username = EXCLUDED.username, from_name = EXCLUDED.from_name, from_email = EXCLUDED.from_email,
                reply_to = EXCLUDED.reply_to,
                password_encrypted = CASE WHEN :keep THEN smtp_settings.password_encrypted ELSE EXCLUDED.password_encrypted END',
            [
                'host' => $fields['host'],
                'port' => $fields['port'],
                'encryption' => $fields['encryption'],
                'username' => $fields['username'],
                'from_name' => $fields['from_name'],
                'from_email' => $fields['from_email'],
                'reply_to' => $fields['reply_to'],
                'password' => $keep ? null : $passwordEncrypted,
                'keep' => $keep,
            ],
        );
    }

    public function recordTest(string $result): void
    {
        $this->db->execute(
            'UPDATE smtp_settings SET last_tested_at = now(), last_test_result = ? WHERE id = 1',
            [mb_substr($result, 0, 1000)],
        );
    }
}
