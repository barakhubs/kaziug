<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\ContactMessage;

/** Delivers Contact and CV-help form messages to the site's contact email. Nothing is stored. */
interface ContactServiceInterface
{
    /** Whether messages can be delivered at all (contact email and SMTP both set). */
    public function isAvailable(): bool;

    /**
     * @return int 0 when sent; otherwise seconds the visitor's IP must wait (rate limited)
     * @throws \App\Mail\MailException when the email could not be sent
     */
    public function send(ContactMessage $message, string $ip): int;
}
