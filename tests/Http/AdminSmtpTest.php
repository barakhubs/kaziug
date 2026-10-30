<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\AdminHttpTestCase;

final class AdminSmtpTest extends AdminHttpTestCase
{
    private const PASSWORD = 'Sup3r-Secret-SMTP';

    /** @return array<string, string> */
    private function form(array $overrides = []): array
    {
        return [...[
            'host' => 'smtp.example.com',
            'port' => '587',
            'encryption' => 'tls',
            'username' => 'mailer@example.com',
            'password' => self::PASSWORD,
            'from_name' => 'KaziUG Jobs',
            'from_email' => 'jobs@example.com',
            'reply_to' => 'hello@example.com',
        ], ...$overrides];
    }

    public function testNotConfiguredInitially(): void
    {
        $body = $this->get('/admin/settings/smtp')->body();

        $this->assertStringContainsString('Mail is not configured', $body);
    }

    public function testPasswordIsEncryptedAndNeverShown(): void
    {
        $this->assertRedirect('/admin/settings/smtp', $this->post('/admin/settings/smtp', $this->form()));

        $stored = (string) $this->db->fetchValue('SELECT password_encrypted FROM smtp_settings');
        $this->assertNotSame('', $stored);
        $this->assertStringNotContainsString(self::PASSWORD, $stored);
        $this->assertStringNotContainsString(self::PASSWORD, base64_decode($stored));

        $page = $this->get('/admin/settings/smtp')->body();
        $this->assertStringContainsString('Mail is configured', $page);
        $this->assertStringContainsString('•••••••• (saved, leave blank to keep)', $page);
        $this->assertStringNotContainsString(self::PASSWORD, $page);
        $this->assertStringNotContainsString($stored, $page);

        $audit = (string) $this->db->fetchValue("SELECT metadata FROM audit_logs WHERE action = 'smtp.updated'");
        $this->assertStringNotContainsString(self::PASSWORD, $audit);
        $this->assertStringContainsString('replaced', $audit);
    }

    public function testBlankPasswordKeepsSavedOneAndClearRemovesIt(): void
    {
        $this->post('/admin/settings/smtp', $this->form());
        $stored = $this->db->fetchValue('SELECT password_encrypted FROM smtp_settings');

        $this->post('/admin/settings/smtp', $this->form(['password' => '', 'host' => 'smtp2.example.com']));
        $this->assertSame($stored, $this->db->fetchValue('SELECT password_encrypted FROM smtp_settings'));
        $this->assertSame('smtp2.example.com', $this->db->fetchValue('SELECT host FROM smtp_settings'));

        $this->post('/admin/settings/smtp', $this->form(['password' => '', 'clear_password' => '1']));
        $this->assertNull($this->db->fetchValue('SELECT password_encrypted FROM smtp_settings'));
    }

    public function testValidationRejectsBadValuesWithoutEchoingPassword(): void
    {
        $response = $this->post('/admin/settings/smtp', $this->form(['port' => '99999', 'from_email' => 'nope', 'from_name' => "Evil\r\nBcc: x@y.z", 'host' => 'bad host!']));

        $this->assertStatus(422, $response);
        foreach (['port', 'from_email', 'from_name', 'host'] as $field) {
            $this->assertStringContainsString("id=\"{$field}-error\"", $response->body());
        }
        $this->assertStringNotContainsString(self::PASSWORD, $response->body());
        $this->assertNull($this->db->fetchValue('SELECT host FROM smtp_settings'));
    }

    public function testSendTestEmailSuccessUsesDecryptedSettings(): void
    {
        $this->post('/admin/settings/smtp', $this->form());

        $this->assertRedirect('/admin/settings/smtp', $this->post('/admin/settings/smtp/test', ['test_to' => 'me@example.org']));

        $this->assertSame(['me@example.org'], $this->mail->recipients());
        $this->assertSame(self::PASSWORD, $this->mail->lastConfig?->password);
        $this->assertSame('tls', $this->mail->lastConfig?->encryption);
        $page = $this->get('/admin/settings/smtp')->body();
        $this->assertStringContainsString('OK: test email sent to me@example.org.', $page);
        $this->assertSame(1, $this->auditCount('smtp.tested'));
    }

    public function testSendTestEmailFailureIsShown(): void
    {
        $this->post('/admin/settings/smtp', $this->form());
        $this->mail->failAlways = true;

        $this->post('/admin/settings/smtp/test', ['test_to' => 'me@example.org']);

        $page = $this->get('/admin/settings/smtp')->body();
        $this->assertStringContainsString('Failed: SMTP connect() failed.', $page);
        $this->assertStringStartsWith('Failed', (string) $this->db->fetchValue('SELECT last_test_result FROM smtp_settings'));
    }

    public function testSettingsTabsHighlightSmtp(): void
    {
        $page = $this->get('/admin/settings/smtp')->body();

        $this->assertStringContainsString('class="tab on" href="/admin/settings/smtp" aria-current="page"', $page);
        $this->assertStringContainsString('class="nav-btn active" href="/admin/settings" aria-current="page"', $page);
        $this->assertStringNotContainsString('class="tab on" href="/admin/settings"', $page);
    }
}
