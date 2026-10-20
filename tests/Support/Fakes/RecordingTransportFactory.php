<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Mail\MailerInterface;
use App\Mail\MailException;
use App\Mail\MailMessage;
use App\Mail\SmtpConfig;
use App\Mail\TransportFactoryInterface;

/** Captures every email instead of sending it; can be told to fail. */
final class RecordingTransportFactory implements TransportFactoryInterface, MailerInterface
{
    /** @var list<MailMessage> */
    public array $sent = [];

    public ?SmtpConfig $lastConfig = null;

    /** @var list<string> error messages to throw for the next sends (one per send) */
    public array $failures = [];

    public bool $failAlways = false;

    public function create(SmtpConfig $config): MailerInterface
    {
        $this->lastConfig = $config;

        return $this;
    }

    public function send(MailMessage $message): void
    {
        if ($this->failAlways) {
            throw new MailException('SMTP connect() failed.');
        }
        if ($this->failures !== []) {
            throw new MailException(array_shift($this->failures));
        }
        $this->sent[] = $message;
    }

    /** @return list<string> */
    public function recipients(): array
    {
        return array_map(static fn (MailMessage $m): string => $m->toEmail, $this->sent);
    }
}
