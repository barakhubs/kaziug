<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Mail\EmailComposer;
use App\Mail\MailerInterface;
use App\Services\Contracts\SettingsReaderInterface;

/** Sends the double opt-in confirmation email. */
final class SubscriptionMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly EmailComposer $composer,
        private readonly SettingsReaderInterface $settings,
        private readonly Config $config,
    ) {
    }

    /** @throws \App\Mail\MailException */
    public function sendConfirmation(string $email, ?string $name, #[\SensitiveParameter] string $confirmToken): void
    {
        $site = $this->settings->site()->siteName;
        $url = $this->config->string('app.url') . '/subscribe/confirm/' . $confirmToken;
        $greeting = $name !== null && $name !== '' ? "Hi {$name}," : 'Hello,';
        $hours = SubscriptionService::CONFIRM_TTL_SECONDS / 3600;

        $html = '<p>' . e($greeting) . '</p>'
            . '<p>Please confirm that you want to receive job alerts from <strong>' . e($site) . '</strong>.</p>'
            . '<p style="margin:24px 0;"><a href="' . e($url) . '" style="background:#f2b233;color:#1c2430;font-weight:700;'
            . 'padding:12px 20px;border-radius:8px;text-decoration:none;display:inline-block;">Confirm subscription</a></p>'
            . '<p style="color:#5d6675;font-size:13px;">This link works for ' . e($hours) . ' hours. If you did not ask for this, ignore this email and you will not be subscribed.</p>';
        $text = "{$greeting}\n\nPlease confirm that you want to receive job alerts from {$site}:\n\n{$url}\n\n"
            . "This link works for {$hours} hours. If you did not ask for this, ignore this email and you will not be subscribed.";

        $this->mailer->send($this->composer->compose(
            $email,
            $name,
            "Confirm your {$site} job alerts",
            'One click to confirm your subscription.',
            $html,
            $text,
            null,
        ));
    }
}
