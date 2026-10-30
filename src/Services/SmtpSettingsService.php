<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database\DbTime;
use App\Core\Log\LoggerInterface;
use App\Domain\Actor;
use App\Domain\SmtpSettings;
use App\Mail\MailException;
use App\Mail\MailMessage;
use App\Mail\SmtpConfig;
use App\Mail\TransportFactoryInterface;
use App\Repositories\Contracts\SmtpSettingsRepositoryInterface;
use App\Services\Contracts\AuditServiceInterface;
use App\Services\Contracts\SettingsReaderInterface;
use App\Services\Contracts\SmtpSettingsServiceInterface;
use RuntimeException;

final class SmtpSettingsService implements SmtpSettingsServiceInterface
{
    public function __construct(
        private readonly SmtpSettingsRepositoryInterface $repository,
        private readonly SecretBox $secrets,
        private readonly TransportFactoryInterface $transports,
        private readonly SettingsReaderInterface $site,
        private readonly AuditServiceInterface $audit,
        private readonly LoggerInterface $logger,
        private readonly Config $config,
    ) {
    }

    public function settings(): SmtpSettings
    {
        $row = $this->repository->find();
        if ($row === null) {
            return new SmtpSettings();
        }

        return new SmtpSettings(
            (string) $row['host'],
            (int) $row['port'],
            (string) $row['encryption'],
            (string) $row['username'],
            ($row['password_encrypted'] ?? '') !== '',
            (string) $row['from_name'],
            (string) $row['from_email'],
            (string) $row['reply_to'],
            DbTime::fromDb($row['last_tested_at']),
            $row['last_test_result'] === null ? null : (string) $row['last_test_result'],
        );
    }

    public function update(array $fields, #[\SensitiveParameter] ?string $newPassword, bool $clearPassword, Actor $actor): void
    {
        $before = $this->settings();
        $password = match (true) {
            $newPassword !== null && $newPassword !== '' => $this->secrets->encrypt($newPassword),
            $clearPassword => null,
            default => false,
        };
        $this->repository->save($fields, $password);

        $changed = [];
        foreach (['host' => $before->host, 'port' => $before->port, 'encryption' => $before->encryption, 'username' => $before->username,
            'from_name' => $before->fromName, 'from_email' => $before->fromEmail, 'reply_to' => $before->replyTo] as $key => $old) {
            if ($fields[$key] !== $old) {
                $changed[] = $key;
            }
        }
        $this->audit->record($actor, 'smtp.updated', 'smtp_settings', 1, [
            'changed' => $changed,
            'password' => is_string($password) ? 'replaced' : ($password === null ? 'cleared' : 'unchanged'),
        ]);
    }

    public function sendTest(string $to, Actor $actor): array
    {
        try {
            $config = $this->config() ?? throw MailException::notConfigured();
            $siteName = $this->site->site()->siteName;
            $time = date('j M Y, H:i');
            $this->transports->create($config)->send(new MailMessage(
                $to,
                "Test email from {$siteName}",
                '<p>This is a test email from <strong>' . e($siteName) . '</strong>, sent at ' . e($time) . '.</p>'
                . '<p>If you can read this, the SMTP settings work.</p>',
                "This is a test email from {$siteName}, sent at {$time}.\n\nIf you can read this, the SMTP settings work.",
            ));
            $result = ['ok' => true, 'message' => "OK: test email sent to {$to}."];
        } catch (MailException $e) {
            $result = ['ok' => false, 'message' => 'Failed: ' . $e->getMessage()];
        }

        $this->repository->recordTest($result['message']);
        $this->audit->record($actor, 'smtp.tested', 'smtp_settings', 1, ['ok' => $result['ok'], 'to' => $to]);

        return $result;
    }

    public function config(): ?SmtpConfig
    {
        $row = $this->repository->find();
        if ($row === null || $row['host'] === '' || $row['from_email'] === '') {
            return null;
        }

        $password = '';
        if (($row['password_encrypted'] ?? '') !== '') {
            try {
                $password = $this->secrets->decrypt((string) $row['password_encrypted']);
            } catch (RuntimeException $e) {
                $this->logger->error('SMTP password could not be decrypted', ['error' => $e->getMessage()]);
                throw new MailException('The saved SMTP password can no longer be read (APP_KEY may have changed). Enter it again in SMTP settings.');
            }
        }

        return new SmtpConfig(
            (string) $row['host'],
            (int) $row['port'],
            (string) $row['encryption'],
            (string) $row['username'],
            $password,
            (string) $row['from_email'],
            (string) $row['from_name'] !== '' ? (string) $row['from_name'] : $this->site->site()->siteName,
            (string) $row['reply_to'],
            $this->config->int('mail.timeout', 15),
        );
    }
}
