<?php

declare(strict_types=1);

namespace App\Domain;

/** One claimed delivery: who to send which newsletter to, and how many tries came before. */
final class Recipient
{
    public function __construct(
        public readonly int $deliveryId,
        public readonly int $newsletterId,
        public readonly int $subscriberId,
        public readonly string $email,
        public readonly ?string $name,
        public readonly string $unsubscribeToken,
        public readonly bool $stillActive,
        public readonly int $attempts,
    ) {
    }
}
