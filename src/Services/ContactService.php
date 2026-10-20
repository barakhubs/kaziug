<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Format;
use App\Domain\ContactMessage;
use App\Domain\ThrottlePolicy;
use App\Mail\MailerInterface;
use App\Mail\MailException;
use App\Mail\MailMessage;
use App\Services\Contracts\ContactServiceInterface;
use App\Services\Contracts\SettingsReaderInterface;
use App\Services\Contracts\SmtpSettingsServiceInterface;
use App\Services\Contracts\ThrottleInterface;

final class ContactService implements ContactServiceInterface
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly SettingsReaderInterface $settings,
        private readonly SmtpSettingsServiceInterface $smtp,
        private readonly ThrottleInterface $throttle,
    ) {
    }

    public function isAvailable(): bool
    {
        return $this->settings->site()->contactEmail !== '' && $this->smtp->settings()->isConfigured();
    }

    public function send(ContactMessage $message, string $ip): int
    {
        if (!$this->isAvailable()) {
            throw new MailException('The contact form is not set up yet.');
        }

        $key = 'contact:ip:' . $ip;
        $locked = $this->throttle->lockedFor($key);
        if ($locked > 0) {
            return $locked;
        }
        $lock = $this->throttle->hit($key, ThrottlePolicy::contactPerIp());
        if ($lock > 0) {
            return $lock;
        }

        $site = $this->settings->site();
        $isCv = $message->topic === ContactMessage::TOPIC_CV;
        $subject = $isCv
            ? "[{$site->siteName}] CV help request from {$message->name}"
            : "[{$site->siteName}] Contact form message from {$message->name}";

        $rows = [
            'Name' => $message->name,
            'Email' => $message->email,
            'Phone / WhatsApp' => $message->phone ?? '—',
        ];
        if ($isCv) {
            $rows['Service'] = 'CV fix (' . Format::money($site->cvServicePrice) . ')';
        }

        $html = '<p>' . ($isCv ? 'New CV help request' : 'New message') . ' from the website:</p><table cellpadding="4">';
        $text = ($isCv ? 'New CV help request' : 'New message') . " from the website:\n\n";
        foreach ($rows as $label => $value) {
            $html .= '<tr><td><strong>' . e($label) . '</strong></td><td>' . e($value) . '</td></tr>';
            $text .= "{$label}: {$value}\n";
        }
        $html .= '</table><p><strong>Message</strong></p><p>' . nl2br(e($message->message)) . '</p>'
            . '<p style="color:#5d6675;font-size:13px;">Reply to this email to answer ' . e($message->name) . ' directly.</p>';
        $text .= "\nMessage:\n{$message->message}\n\nReply to this email to answer {$message->name} directly.\n";

        $this->mailer->send(new MailMessage($site->contactEmail, $subject, $html, $text, null, [], $message->email));

        return 0;
    }
}
