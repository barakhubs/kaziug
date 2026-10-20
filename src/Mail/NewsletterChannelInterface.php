<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Newsletter;
use App\Domain\Recipient;

/**
 * How a newsletter reaches one recipient. The worker only knows this interface: adding a
 * channel (SMS, WhatsApp...) means a new implementation bound in config/container.php
 * (or a composite that fans out), with no change to the worker or the email channel.
 */
interface NewsletterChannelInterface
{
    /** @throws MailException on a failure worth retrying */
    public function deliver(Newsletter $newsletter, Recipient $recipient): void;
}
