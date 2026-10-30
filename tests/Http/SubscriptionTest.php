<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Services\FormTimer;
use App\Services\TokenService;
use Tests\Support\HttpTestCase;

final class SubscriptionTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->execute(
            "INSERT INTO smtp_settings (id, host, port, encryption, from_email) VALUES (1, 'smtp.test', 25, 'none', 'jobs@kazi.test')",
        );
        $this->db->execute("UPDATE settings SET value = '\"Plot 5, Jinja Road, Kampala\"' WHERE `key` = 'physical_address'");
    }

    /** @return array<string, string> */
    private function form(array $overrides = []): array
    {
        return [...[
            'email' => 'Amina@Example.com',
            'name' => 'Amina',
            'consent' => '1',
            '_ts' => $this->container->get(FormTimer::class)->issue(time() - 10),
            'website' => '',
        ], ...$overrides];
    }

    private function confirmToken(): string
    {
        $text = end($this->mail->sent)->text;
        preg_match('#/subscribe/confirm/([A-Za-z0-9_-]{43})#', $text, $m);

        return $m[1] ?? '';
    }

    private function subscriberStatus(string $email = 'amina@example.com'): ?string
    {
        $value = $this->db->fetchValue('SELECT status FROM subscribers WHERE lower(email) = ?', [$email]);

        return $value === null ? null : (string) $value;
    }

    public function testFooterHasSubscribeFormWithSpamTraps(): void
    {
        $body = $this->get('/')->body();

        $this->assertStringContainsString('action="/subscribe"', $body);
        $this->assertStringContainsString('name="website"', $body);
        $this->assertMatchesRegularExpression('/name="_ts" value="\d+\.[a-f0-9]{64}"/', $body);
    }

    public function testDoubleOptInFlow(): void
    {
        $response = $this->post('/subscribe', $this->form());
        $this->assertStatus(200, $response);
        $this->assertStringContainsString('Check your inbox', $response->body());

        $row = $this->db->fetchOne('SELECT * FROM subscribers');
        $this->assertSame('amina@example.com', $row['email']);
        $this->assertSame('pending', $row['status']);
        $this->assertNotNull($row['consent_at']);
        $this->assertSame('127.0.0.1', $row['consent_ip']);

        $this->assertSame(['amina@example.com'], $this->mail->recipients());
        $token = $this->confirmToken();
        $this->assertNotSame('', $token);
        $this->assertSame($this->container->get(TokenService::class)->hash($token), $row['confirm_token_hash'], 'only the hash is stored');
        $this->assertStringNotContainsString($token, json_encode($row));

        // Opening the link (or a mail scanner prefetching it) changes nothing.
        $page = $this->get("/subscribe/confirm/{$token}");
        $this->assertStringContainsString('Confirm subscription', $page->body());
        $this->assertSame('pending', $this->subscriberStatus());

        $this->assertStringContainsString('You’re subscribed', $this->post("/subscribe/confirm/{$token}")->body());
        $this->assertSame('active', $this->subscriberStatus());
        $this->assertNull($this->db->fetchValue('SELECT confirm_token_hash FROM subscribers'));

        $this->assertStatus(404, $this->post("/subscribe/confirm/{$token}"), 'token is single use');
    }

    public function testConfirmationEmailContent(): void
    {
        $this->post('/subscribe', $this->form());
        $message = $this->mail->sent[0];

        $this->assertStringContainsString('Confirm your KaziUG job alerts', $message->subject);
        $this->assertStringContainsString('Plot 5, Jinja Road, Kampala', $message->html);
        $this->assertStringContainsString('http://localhost/subscribe/confirm/', $message->html);
        $this->assertStringContainsString('Hi Amina,', $message->text);
    }

    public function testExpiredConfirmationLinkIsRejected(): void
    {
        $this->post('/subscribe', $this->form());
        $token = $this->confirmToken();
        $this->db->execute("UPDATE subscribers SET confirm_sent_at = NOW(6) - INTERVAL 49 HOUR");

        $this->assertStatus(404, $this->post("/subscribe/confirm/{$token}"));
        $this->assertSame('pending', $this->subscriberStatus());
    }

    public function testBotsAreSilentlyIgnored(): void
    {
        $honeypot = $this->post('/subscribe', $this->form(['website' => 'http://spam.example']));
        $tooFast = $this->post('/subscribe', $this->form(['_ts' => $this->container->get(FormTimer::class)->issue(time())]));
        $forged = $this->post('/subscribe', $this->form(['_ts' => '1700000000.' . str_repeat('0', 64)]));

        foreach ([$honeypot, $tooFast, $forged] as $response) {
            $this->assertStatus(200, $response);
            $this->assertStringContainsString('Check your inbox', $response->body());
        }
        $this->assertSame(0, (int) $this->db->fetchValue('SELECT count(*) FROM subscribers'));
        $this->assertSame([], $this->mail->sent);
    }

    public function testValidationAndCsrf(): void
    {
        $this->assertStatus(422, $this->post('/subscribe', $this->form(['consent' => ''])));
        $this->assertStatus(422, $this->post('/subscribe', $this->form(['email' => 'nope'])));
        $this->assertStatus(419, $this->post('/subscribe', $this->form(), withToken: false));
        $this->assertSame(0, (int) $this->db->fetchValue('SELECT count(*) FROM subscribers'));
    }

    public function testRateLimitedPerIp(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->assertStatus(200, $this->post('/subscribe', $this->form(['email' => "person{$i}@example.com"])));
        }
        $limited = $this->post('/subscribe', $this->form(['email' => 'person6@example.com']));

        $this->assertStatus(429, $limited);
        $this->assertNotNull($limited->header('Retry-After'));
        $this->assertNull($this->subscriberStatus('person6@example.com'));
    }

    public function testResubscribingDoesNotRevealOrDuplicate(): void
    {
        $this->post('/subscribe', $this->form());
        $this->post('/subscribe/confirm/' . $this->confirmToken());
        $sentBefore = count($this->mail->sent);

        $again = $this->post('/subscribe', $this->form(['email' => 'AMINA@example.com']));

        $this->assertStringContainsString('Check your inbox', $again->body());
        $this->assertCount($sentBefore, $this->mail->sent, 'active subscribers get no new email');
        $this->assertSame(1, (int) $this->db->fetchValue('SELECT count(*) FROM subscribers'));
    }

    public function testUnsubscribeLinkAndOneClickPost(): void
    {
        $this->post('/subscribe', $this->form());
        $this->post('/subscribe/confirm/' . $this->confirmToken());
        $token = (string) $this->db->fetchValue('SELECT unsubscribe_token FROM subscribers');

        $page = $this->get("/unsubscribe/{$token}");
        $this->assertStringContainsString('amina@example.com', $page->body());
        $this->assertSame('active', $this->subscriberStatus(), 'GET changes nothing');

        // RFC 8058: mail clients POST without a CSRF token or cookies.
        $this->cookies = [];
        $response = $this->post("/unsubscribe/{$token}", ['List-Unsubscribe' => 'One-Click'], withToken: false);
        $this->assertStatus(200, $response);
        $this->assertSame('unsubscribed', $this->subscriberStatus());
        $this->assertNotNull($this->db->fetchValue('SELECT unsubscribed_at FROM subscribers'));

        $this->assertStatus(404, $this->get('/unsubscribe/' . str_repeat('x', 43)));
    }

    public function testUnsubscribedPersonCanSubscribeAgainWithFreshConsent(): void
    {
        $this->post('/subscribe', $this->form());
        $this->db->execute("UPDATE subscribers SET status = 'unsubscribed', unsubscribed_at = NOW(6), consent_at = NOW(6) - INTERVAL 1 YEAR");

        $this->post('/subscribe', $this->form());

        $this->assertSame('pending', $this->subscriberStatus());
        $this->assertTrue((bool) $this->db->fetchValue("SELECT consent_at > NOW(6) - INTERVAL 1 MINUTE FROM subscribers"));
        $this->assertCount(2, $this->mail->sent);
    }

    public function testMailFailureKeepsSubscriberPendingAndShowsSamePage(): void
    {
        $this->mail->failAlways = true;

        $response = $this->post('/subscribe', $this->form());

        $this->assertStatus(200, $response);
        $this->assertSame('pending', $this->subscriberStatus());
    }
}
