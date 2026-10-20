<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Config;
use App\Domain\Actor;
use App\Domain\DomainException;
use App\Domain\NewsletterStatus;
use App\Repositories\Contracts\LockInterface;
use App\Services\Contracts\NewsletterServiceInterface;
use App\Services\NewsletterWorker;
use DateTimeImmutable;
use Tests\Support\DatabaseTestCase;

/** Newsletter queueing and the background worker, against the real database. */
final class NewsletterQueueTest extends DatabaseTestCase
{
    private NewsletterServiceInterface $service;
    private Actor $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db->execute("INSERT INTO smtp_settings (id, host, port, encryption, from_email) VALUES (1, 'smtp.test', 25, 'none', 'jobs@kazi.test')");
        $this->db->execute("UPDATE settings SET value = '\"Plot 5, Jinja Road, Kampala\"' WHERE key = 'physical_address'");
        foreach (['a' => 'active', 'b' => 'active', 'c' => 'active', 'p' => 'pending', 'u' => 'unsubscribed'] as $name => $status) {
            $this->db->execute(
                'INSERT INTO subscribers (email, status, unsubscribe_token) VALUES (?, ?, ?)',
                ["{$name}@example.com", $status, str_pad($name, 43, 't')],
            );
        }
        $this->service = $this->container->get(NewsletterServiceInterface::class);
        $this->actor = Actor::console();
    }

    private function newsletter(string $body = '<p>Hello <strong>job seekers</strong></p>'): int
    {
        return $this->service->create(['subject' => 'October jobs', 'preheader' => 'Fresh roles', 'body_html' => $body, 'body_text' => null], $this->actor)->id;
    }

    private function worker(int $ratePerMinute = 60): NewsletterWorker
    {
        $config = $this->container->get(Config::class)->with('newsletter.rate_per_minute', $ratePerMinute);
        $this->container->instance(Config::class, $config);

        return new NewsletterWorker(
            $this->container->get(\App\Repositories\Contracts\NewsletterRepositoryInterface::class),
            $this->container->get(\App\Repositories\Contracts\DeliveryRepositoryInterface::class),
            $this->container->get(\App\Mail\NewsletterChannelInterface::class),
            $this->container->get(LockInterface::class),
            $this->container->get(\App\Core\Log\LoggerInterface::class),
            $config,
        );
    }

    private function newsletterStatus(int $id): string
    {
        return (string) $this->db->fetchValue('SELECT status FROM newsletters WHERE id = ?', [$id]);
    }

    public function testSendNowQueuesOnlyActiveSubscribersAndSendsNothingInRequest(): void
    {
        $id = $this->newsletter();

        $this->assertSame(3, $this->service->sendNow($id, $this->actor));
        $this->assertSame('sending', $this->newsletterStatus($id));
        $this->assertSame([], $this->mail->sent, 'the request only queues');
        $this->assertSame(3, $this->service->counts($id)->queued);

        $this->expectException(DomainException::class);
        $this->service->sendNow($id, $this->actor);
    }

    public function testWorkerSendsPersonalisedEmailsAndFinishes(): void
    {
        $id = $this->newsletter('<p>Hi</p><script>alert(1)</script>');
        $this->service->sendNow($id, $this->actor);

        $stats = $this->worker()->run();

        $this->assertSame(3, $stats['sent']);
        $this->assertSame(1, $stats['finished']);
        $this->assertSame('sent', $this->newsletterStatus($id));
        $this->assertEqualsCanonicalizing(['a@example.com', 'b@example.com', 'c@example.com'], $this->mail->recipients());

        $message = $this->mail->sent[0];
        $token = (string) $this->db->fetchValue('SELECT unsubscribe_token FROM subscribers WHERE email = ?', [$message->toEmail]);
        $this->assertStringContainsString("http://localhost/unsubscribe/{$token}", $message->html);
        $this->assertStringContainsString("http://localhost/unsubscribe/{$token}", $message->text);
        $this->assertSame("<http://localhost/unsubscribe/{$token}>", $message->headers['List-Unsubscribe']);
        $this->assertSame('List-Unsubscribe=One-Click', $message->headers['List-Unsubscribe-Post']);
        $this->assertStringContainsString('Plot 5, Jinja Road, Kampala', $message->html);
        $this->assertStringContainsString('Plot 5, Jinja Road, Kampala', $message->text);
        $this->assertStringNotContainsString('<script', $message->html);
        $this->assertSame('October jobs', $message->subject);
    }

    public function testRateLimitPerMinute(): void
    {
        $id = $this->newsletter();
        $this->service->sendNow($id, $this->actor);
        $worker = $this->worker(2);

        $this->assertSame(2, $worker->run()['sent']);
        $this->assertSame(0, $worker->run()['sent'], 'budget for this minute is used up');
        $this->assertSame('sending', $this->newsletterStatus($id));

        $this->db->execute("UPDATE newsletter_deliveries SET sent_at = now() - interval '2 minutes' WHERE status = 'sent'");
        $this->assertSame(1, $worker->run()['sent']);
        $this->assertSame('sent', $this->newsletterStatus($id));
    }

    public function testFailedSendsRetryWithBackoffThenFailPermanently(): void
    {
        $this->db->execute("UPDATE subscribers SET status = 'pending' WHERE email IN ('b@example.com', 'c@example.com')");
        $id = $this->newsletter();
        $this->service->sendNow($id, $this->actor);
        $worker = $this->worker();
        $this->mail->failAlways = true;

        $first = $worker->run();
        $this->assertSame(1, $first['retrying']);
        $row = $this->db->fetchOne('SELECT status, attempts, last_error, next_attempt_at > now() + interval \'50 seconds\' AS later FROM newsletter_deliveries');
        $this->assertSame(['queued', 1, 'SMTP connect() failed.', true], [$row['status'], $row['attempts'], $row['last_error'], $row['later']]);

        $this->assertSame(0, $worker->run()['retrying'], 'not due yet: backoff');

        $this->db->execute('UPDATE newsletter_deliveries SET next_attempt_at = now()');
        $this->assertSame(1, $worker->run()['retrying']);
        $this->assertTrue((bool) $this->db->fetchValue("SELECT next_attempt_at > now() + interval '4 minutes' FROM newsletter_deliveries"), 'second backoff is 5 minutes');

        $this->db->execute('UPDATE newsletter_deliveries SET next_attempt_at = now()');
        $third = $worker->run();
        $this->assertSame(1, $third['failed']);
        $this->assertSame(['failed', 3], array_values($this->db->fetchOne('SELECT status, attempts FROM newsletter_deliveries')));
        $this->assertSame('failed', $this->newsletterStatus($id), 'nothing went out at all');
        $this->assertSame([['email' => 'a@example.com', 'attempts' => 3, 'last_error' => 'SMTP connect() failed.']], $this->service->permanentFailures($id));
    }

    public function testRetrySucceedsAfterTransientFailure(): void
    {
        $id = $this->newsletter();
        $this->service->sendNow($id, $this->actor);
        $worker = $this->worker();
        $this->mail->failures = ['421 Try again later'];

        $stats = $worker->run();
        $this->assertSame([2, 1], [$stats['sent'], $stats['retrying']]);

        $this->db->execute("UPDATE newsletter_deliveries SET next_attempt_at = now() WHERE status = 'queued'");
        $this->assertSame(1, $worker->run()['sent']);
        $counts = $this->service->counts($id);
        $this->assertSame([0, 3, 0], [$counts->queued, $counts->sent, $counts->failed]);
        $this->assertSame('sent', $this->newsletterStatus($id));
    }

    public function testUnsubscribingAfterQueueingPreventsTheSend(): void
    {
        $id = $this->newsletter();
        $this->service->sendNow($id, $this->actor);
        $this->db->execute("UPDATE subscribers SET status = 'unsubscribed' WHERE email = 'b@example.com'");

        $this->worker()->run();

        $this->assertNotContains('b@example.com', $this->mail->recipients());
        $this->assertSame('Not sent: the subscriber is no longer active.', $this->service->permanentFailures($id)[0]['last_error']);
        $this->assertSame('sent', $this->newsletterStatus($id));
    }

    public function testScheduledNewsletterIsQueuedOnlyWhenDue(): void
    {
        $id = $this->newsletter();
        $this->service->schedule($id, new DateTimeImmutable('+2 hours'), $this->actor);
        $worker = $this->worker();

        $this->assertSame(0, $worker->run()['promoted']);
        $this->assertSame('scheduled', $this->newsletterStatus($id));

        $this->db->execute("UPDATE newsletters SET scheduled_at = now() - interval '1 minute'");
        $stats = $worker->run();
        $this->assertSame([1, 3], [$stats['promoted'], $stats['sent']]);
        $this->assertSame('sent', $this->newsletterStatus($id));
        $this->assertNotNull($this->db->fetchValue('SELECT scheduled_at FROM newsletters'), 'schedule kept for history');
    }

    public function testScheduleValidationAndUnschedule(): void
    {
        $id = $this->newsletter();
        try {
            $this->service->schedule($id, new DateTimeImmutable('-1 hour'), $this->actor);
            $this->fail('past time accepted');
        } catch (DomainException $e) {
            $this->assertSame('scheduled_at', $e->field);
        }

        $this->service->schedule($id, new DateTimeImmutable('+1 day'), $this->actor);
        $this->service->unschedule($id, $this->actor);
        $this->assertSame('draft', $this->newsletterStatus($id));
        $this->assertNull($this->db->fetchValue('SELECT scheduled_at FROM newsletters'));
    }

    public function testSendingRequiresAddressAndSmtp(): void
    {
        $id = $this->newsletter();
        $this->db->execute("UPDATE settings SET value = '\"\"' WHERE key = 'physical_address'");
        $this->container->get(\App\Services\Contracts\SettingsReaderInterface::class)->forget();

        try {
            $this->service->sendNow($id, $this->actor);
            $this->fail('sent without a physical address');
        } catch (DomainException $e) {
            $this->assertStringContainsString('physical address', $e->getMessage());
        }
        $this->assertSame(NewsletterStatus::Draft->value, $this->newsletterStatus($id));
    }

    public function testOnlyOneWorkerRunsAtATime(): void
    {
        $id = $this->newsletter();
        $this->service->sendNow($id, $this->actor);

        // Another connection holds the worker lock.
        $other = new \App\Core\Database\Database($this->container->get(Config::class)->get('db'));
        $other->fetchValue("SELECT pg_advisory_lock(hashtext('newsletter-worker'))");

        try {
            $this->assertTrue($this->worker()->run()['locked']);
            $this->assertSame([], $this->mail->sent);
        } finally {
            $other->fetchValue("SELECT pg_advisory_unlock(hashtext('newsletter-worker'))");
        }
        $this->assertSame(3, $this->worker()->run()['sent']);
    }

    public function testEditingIsBlockedOnceSending(): void
    {
        $id = $this->newsletter();
        $this->service->sendNow($id, $this->actor);

        $this->expectException(DomainException::class);
        $this->service->update($id, ['subject' => 'x', 'preheader' => '', 'body_html' => '<p>x</p>', 'body_text' => null], $this->actor);
    }
}
