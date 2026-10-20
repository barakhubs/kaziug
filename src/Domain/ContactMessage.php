<?php

declare(strict_types=1);

namespace App\Domain;

/** A validated message from the Contact or CV help form. */
final class ContactMessage
{
    public const TOPIC_GENERAL = 'contact';
    public const TOPIC_CV = 'cv-help';

    public function __construct(
        public readonly string $topic,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly string $message,
    ) {
    }
}
