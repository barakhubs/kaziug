<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Config;
use App\Core\View;
use App\Services\Contracts\SettingsReaderInterface;

/**
 * Wraps message content in the branded email layout and adds the footer every email needs:
 * the physical address and, for subscriber mail, a working unsubscribe link and the
 * List-Unsubscribe headers (RFC 2369 / RFC 8058 one-click).
 */
final class EmailComposer
{
    public function __construct(
        private readonly View $view,
        private readonly SettingsReaderInterface $settings,
        private readonly Config $config,
    ) {
    }

    public function unsubscribeUrl(string $token): string
    {
        return $this->config->string('app.url') . '/unsubscribe/' . $token;
    }

    /** @param string $htmlContent safe HTML: escaped or passed through the sanitiser */
    public function compose(
        string $toEmail,
        ?string $toName,
        string $subject,
        string $preheader,
        string $htmlContent,
        string $textContent,
        ?string $unsubscribeToken,
    ): MailMessage {
        $site = $this->settings->site();
        $unsubscribeUrl = $unsubscribeToken === null ? null : $this->unsubscribeUrl($unsubscribeToken);

        $html = $this->view->render('emails/layout', [
            'content' => $htmlContent,
            'site' => $site,
            'preheader' => $preheader,
            'unsubscribeUrl' => $unsubscribeUrl,
            'appUrl' => $this->config->string('app.url'),
        ]);

        $footer = [];
        if ($site->physicalAddress !== '') {
            $footer[] = $site->physicalAddress;
        }
        if ($unsubscribeUrl !== null) {
            $footer[] = "Unsubscribe: {$unsubscribeUrl}";
        }
        $text = rtrim($textContent) . ($footer === [] ? '' : "\n\n--\n" . implode("\n\n", $footer)) . "\n";

        $headers = [];
        if ($unsubscribeUrl !== null) {
            $headers['List-Unsubscribe'] = "<{$unsubscribeUrl}>";
            $headers['List-Unsubscribe-Post'] = 'List-Unsubscribe=One-Click';
        }

        return new MailMessage($toEmail, $subject, $html, $text, $toName, $headers);
    }
}
