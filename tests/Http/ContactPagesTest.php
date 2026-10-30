<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Services\FormTimer;
use Tests\Support\HttpTestCase;

final class ContactPagesTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->execute("INSERT INTO smtp_settings (id, host, port, encryption, from_email) VALUES (1, 'smtp.test', 25, 'none', 'jobs@kazi.test')");
        $this->db->execute("UPDATE settings SET value = '\"hello@kazi.ug\"' WHERE `key` = 'contact_email'");
        $this->db->execute("UPDATE settings SET value = '\"256772123456\"' WHERE `key` = 'whatsapp_number'");
    }

    /** @return array<string, string> */
    private function form(array $overrides = []): array
    {
        return [...[
            'name' => 'Amina N.',
            'email' => 'Amina@Example.com',
            'phone' => '+256 701 000000',
            'message' => "I'd like help with my CV for accounting roles.",
            '_ts' => $this->container->get(FormTimer::class)->issue(time() - 10),
            'website' => '',
        ], ...$overrides];
    }

    public function testPagesShowFormWhatsappAndPrice(): void
    {
        $contact = $this->get('/contact')->body();
        $this->assertStringContainsString('action="/contact"', $contact);
        $this->assertStringContainsString('+256 772 123456', $contact);
        $this->assertStringContainsString('https://wa.me/256772123456?text=', $contact);

        $cv = $this->get('/cv-help')->body();
        $this->assertStringContainsString('UGX 100,000', $cv);
        $this->assertStringContainsString('action="/cv-help"', $cv);
        $this->assertStringContainsString(rawurlencode("I'd like help with my CV (UGX 100,000)."), $cv);

        $this->assertStringContainsString('href="/cv-help"', $this->get('/')->body(), 'linked from the header');
    }

    public function testContactMessageIsEmailedWithVisitorAsReplyTo(): void
    {
        $response = $this->post('/contact', $this->form(['message' => "Hello\n<script>alert(1)</script> team"]));

        $this->assertRedirect('/contact', $response);
        $this->assertCount(1, $this->mail->sent);
        $mail = $this->mail->sent[0];
        $this->assertSame('hello@kazi.ug', $mail->toEmail);
        $this->assertSame('amina@example.com', $mail->replyTo);
        $this->assertSame('[KaziUG] Contact form message from Amina N.', $mail->subject);
        $this->assertStringContainsString('&lt;script&gt;', $mail->html);
        $this->assertStringNotContainsString('<script>', $mail->html);
        $this->assertStringContainsString('+256 701 000000', $mail->text);
        $this->assertStringContainsString('Your message has been sent', $this->get('/contact')->body());
        $this->assertSame(0, (int) $this->db->fetchValue('SELECT count(*) FROM audit_logs'), 'nothing stored');
    }

    public function testCvRequestMentionsServiceAndPrice(): void
    {
        $this->db->execute("UPDATE settings SET value = '150000' WHERE `key` = 'cv_service_price'");

        $this->post('/cv-help', $this->form());

        $mail = $this->mail->sent[0];
        $this->assertSame('[KaziUG] CV help request from Amina N.', $mail->subject);
        $this->assertStringContainsString('CV fix (UGX 150,000)', $mail->text);
        $this->assertStringContainsString('UGX 150,000', $this->get('/cv-help')->body());
    }

    public function testValidationSpamAndRateLimit(): void
    {
        $this->assertStatus(422, $this->post('/contact', $this->form(['email' => 'nope'])));
        $this->assertStatus(422, $this->post('/contact', $this->form(['message' => 'short'])));
        $this->assertStatus(422, $this->post('/contact', $this->form(['phone' => 'call me'])));
        $this->assertStatus(419, $this->post('/contact', $this->form(), withToken: false));

        $this->assertRedirect('/contact', $this->post('/contact', $this->form(['website' => 'spam'])));
        $this->assertRedirect('/contact', $this->post('/contact', $this->form(['_ts' => $this->container->get(FormTimer::class)->issue(time())])));
        $this->assertSame([], $this->mail->sent, 'bots are silently ignored');

        for ($i = 0; $i < 5; $i++) {
            $this->post('/contact', $this->form());
        }
        $this->assertStatus(429, $this->post('/contact', $this->form()));
        $this->assertCount(5, $this->mail->sent);
    }

    public function testFailuresAreReportedAndWhatsappOffered(): void
    {
        $this->mail->failAlways = true;
        $failed = $this->post('/contact', $this->form());
        $this->assertStatus(503, $failed);
        $this->assertStringContainsString('reach us on WhatsApp', $failed->body());
        $this->assertStringContainsString('value="Amina N."', $failed->body(), 'input is kept');

        $this->db->execute("UPDATE settings SET value = '\"\"' WHERE `key` = 'contact_email'");
        $body = $this->get('/contact')->body();
        $this->assertStringNotContainsString('action="/contact"', $body);
        $this->assertStringContainsString('Message us on WhatsApp', $body);
    }

    public function testSettingsValidateWhatsappNumber(): void
    {
        $this->container->get(\App\Services\Contracts\AdminAccountServiceInterface::class)
            ->createAdmin('admin@kazi.ug', 'a long test passphrase', \App\Domain\Actor::console());
        $this->post('/admin/login', ['email' => 'admin@kazi.ug', 'password' => 'a long test passphrase']);
        $base = ['site_name' => 'KaziUG', 'jobs_per_page' => '8', 'cv_service_price' => '120,000'];

        $this->assertStatus(422, $this->post('/admin/settings', [...$base, 'whatsapp_number' => '0772 123456']));
        $this->assertRedirect('/admin/settings', $this->post('/admin/settings', [...$base, 'whatsapp_number' => '+256 (772) 123-456']));

        $this->assertSame('"256772123456"', $this->db->fetchValue("SELECT value FROM settings WHERE `key` = 'whatsapp_number'"));
        $this->assertSame('120000', $this->db->fetchValue("SELECT value FROM settings WHERE `key` = 'cv_service_price'"));
        $this->assertStringContainsString('value="+256772123456"', $this->get('/admin/settings')->body());
    }

    public function testWhatsappChannelButtonFollowsTheSetting(): void
    {
        $channel = 'https://whatsapp.com/channel/0029VbEFW1E002TGE4pQ5Z0U';
        $this->assertStringNotContainsString('class="wa-float"', $this->get('/')->body(), 'hidden until a link is set');

        $this->container->get(\App\Services\Contracts\AdminAccountServiceInterface::class)
            ->createAdmin('admin@kazi.ug', 'a long test passphrase', \App\Domain\Actor::console());
        $this->post('/admin/login', ['email' => 'admin@kazi.ug', 'password' => 'a long test passphrase']);
        $base = ['site_name' => 'KaziUG', 'jobs_per_page' => '8'];

        foreach (['https://evil.example/channel/0029VbEFW1E002', 'http://whatsapp.com/channel/0029VbEFW1E002', 'https://whatsapp.com/channel/x"onclick'] as $bad) {
            $this->assertStatus(422, $this->post('/admin/settings', [...$base, 'whatsapp_channel_url' => $bad]), $bad);
        }
        $this->assertRedirect('/admin/settings', $this->post('/admin/settings', [...$base, 'whatsapp_channel_url' => " {$channel}/ "]));
        $this->assertSame(json_encode($channel, JSON_UNESCAPED_SLASHES), $this->db->fetchValue("SELECT value FROM settings WHERE `key` = 'whatsapp_channel_url'"));

        $this->assertStringNotContainsString('class="wa-float"', $this->get('/admin/settings')->body(), 'never in the back office');
        foreach (['/', '/contact', '/cv-help'] as $path) {
            $this->assertStringContainsString('<a class="wa-float" href="' . $channel . '" target="_blank" rel="noopener"', $this->get($path)->body(), $path);
        }
    }
}
