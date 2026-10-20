<?php

declare(strict_types=1);

namespace App\Mail;

use PHPMailer\PHPMailer\Exception as PhpMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/** The only class that knows about PHPMailer. */
final class PhpMailerTransport implements MailerInterface
{
    public function __construct(private readonly SmtpConfig $config)
    {
    }

    public function send(MailMessage $message): void
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->config->host;
            $mail->Port = $this->config->port;
            $mail->Timeout = $this->config->timeout;
            $mail->SMTPDebug = 0;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->XMailer = ' ';
            match ($this->config->encryption) {
                'ssl' => $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS,
                'tls' => $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS,
                // "none" means none: no opportunistic STARTTLS either.
                default => $mail->SMTPAutoTLS = false,
            };
            if ($this->config->username !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $this->config->username;
                $mail->Password = $this->config->password;
            }

            $mail->setFrom($this->config->fromEmail, $this->config->fromName, false);
            if ($this->config->replyTo !== '') {
                $mail->addReplyTo($this->config->replyTo);
            }
            $mail->addAddress($message->toEmail, $message->toName ?? '');
            foreach ($message->headers as $name => $value) {
                $mail->addCustomHeader($name, $value);
            }

            $mail->isHTML(true);
            $mail->Subject = $message->subject;
            $mail->Body = $message->html;
            $mail->AltBody = $message->text;
            $mail->send();
        } catch (PhpMailerException $e) {
            throw new MailException($this->clean($mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage()), 0, $e);
        } finally {
            $mail->smtpClose();
        }
    }

    /** Keeps server responses readable but never echoes the password. */
    private function clean(string $error): string
    {
        $error = trim(preg_replace('/\s+/', ' ', strip_tags($error)) ?? '');
        if ($this->config->password !== '') {
            $error = str_replace($this->config->password, '[hidden]', $error);
        }

        return mb_substr($error !== '' ? $error : 'Unknown mail error.', 0, 500);
    }
}
