<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\Newsletter;
use App\Domain\Recipient;

final class EmailNewsletterChannel implements NewsletterChannelInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly NewsletterEmailBuilder $builder,
    ) {
    }

    public function deliver(Newsletter $newsletter, Recipient $recipient): void
    {
        $this->mailer->send($this->builder->build($newsletter, $recipient->email, $recipient->name, $recipient->unsubscribeToken));
    }
}
