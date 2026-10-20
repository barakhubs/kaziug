<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final class Subscriber
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly ?string $name,
        public readonly SubscriberStatus $status,
        public readonly ?DateTimeImmutable $consentAt,
        public readonly ?string $consentIp,
        public readonly ?DateTimeImmutable $confirmSentAt,
        public readonly ?DateTimeImmutable $confirmedAt,
        public readonly string $unsubscribeToken,
        public readonly DateTimeImmutable $createdAt,
        public readonly ?DateTimeImmutable $unsubscribedAt,
    ) {
    }

    /** "j***@example.com", for audit records that must not keep the full address. */
    public function maskedEmail(): string
    {
        [$local, $domain] = array_pad(explode('@', $this->email, 2), 2, '');

        return mb_substr($local, 0, 1) . '***@' . $domain;
    }
}
