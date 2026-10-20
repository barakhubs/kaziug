<?php

declare(strict_types=1);

namespace App\Domain;

use DateTimeImmutable;

final class Newsletter
{
    public function __construct(
        public readonly int $id,
        public readonly string $subject,
        public readonly string $preheader,
        public readonly string $bodyHtml,
        public readonly string $bodyText,
        public readonly NewsletterStatus $status,
        public readonly ?DateTimeImmutable $scheduledAt,
        public readonly ?DateTimeImmutable $sentAt,
        public readonly ?string $createdBy,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
    ) {
    }
}
