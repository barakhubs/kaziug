<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Log\LoggerInterface;
use App\Domain\Newsletter;
use App\Domain\NewsletterStatus;
use App\Mail\MailException;
use App\Mail\NewsletterChannelInterface;
use App\Repositories\Contracts\DeliveryRepositoryInterface;
use App\Repositories\Contracts\LockInterface;
use App\Repositories\Contracts\NewsletterRepositoryInterface;
use DateTimeImmutable;
use Throwable;

/**
 * One run of the background sender (bin/console newsletter:work, once a minute):
 *  1. scheduled newsletters whose time has come are queued,
 *  2. due deliveries are sent, at most NEWSLETTER_RATE_PER_MINUTE per rolling minute,
 *  3. failures are retried with backoff (1, 5, 15 min) up to 3 attempts, then marked failed,
 *  4. newsletters with nothing left in the queue are marked sent (or failed if nothing went out).
 */
final class NewsletterWorker
{
    private const LOCK = 'newsletter-worker';
    private const LEASE_SECONDS = 300;

    public function __construct(
        private readonly NewsletterRepositoryInterface $newsletters,
        private readonly DeliveryRepositoryInterface $deliveries,
        private readonly NewsletterChannelInterface $channel,
        private readonly LockInterface $lock,
        private readonly LoggerInterface $logger,
        private readonly Config $config,
    ) {
    }

    /** @return array{locked: bool, promoted: int, sent: int, retrying: int, failed: int, finished: int} */
    public function run(?DateTimeImmutable $now = null): array
    {
        $stats = ['locked' => false, 'promoted' => 0, 'sent' => 0, 'retrying' => 0, 'failed' => 0, 'finished' => 0];
        if (!$this->lock->tryAcquire(self::LOCK)) {
            // Another run is still going (a slow SMTP server); this one simply skips.
            return ['locked' => true] + $stats;
        }

        try {
            $now ??= new DateTimeImmutable();
            $stats['promoted'] = $this->promoteScheduled($now);
            $this->sendBatch($stats);
            $stats['finished'] = $this->finishCompleted();
        } finally {
            $this->lock->release(self::LOCK);
        }

        return $stats;
    }

    private function promoteScheduled(DateTimeImmutable $now): int
    {
        $promoted = 0;
        foreach ($this->newsletters->dueScheduledIds($now) as $id) {
            if ($this->newsletters->transition($id, [NewsletterStatus::Scheduled], NewsletterStatus::Sending)) {
                $queued = $this->deliveries->queueForActiveSubscribers($id);
                $this->logger->info('Scheduled newsletter queued', ['newsletter' => $id, 'deliveries' => $queued]);
                $promoted++;
            }
        }

        return $promoted;
    }

    /** @param array<string, int|bool> $stats */
    private function sendBatch(array &$stats): void
    {
        $rate = $this->config->int('newsletter.rate_per_minute', 60);
        $budget = max(0, $rate - $this->deliveries->sentInLast(60));
        $maxAttempts = $this->config->int('newsletter.max_attempts', 3);
        /** @var list<int> $backoff */
        $backoff = $this->config->get('newsletter.backoff_minutes', [1, 5, 15]);

        /** @var array<int, Newsletter|null> $cache */
        $cache = [];
        foreach ($this->deliveries->claimDue($budget, self::LEASE_SECONDS) as $recipient) {
            $newsletter = $cache[$recipient->newsletterId] ??= $this->newsletters->findById($recipient->newsletterId);

            if (!$recipient->stillActive || $newsletter === null) {
                // Unsubscribed (or removed) after the newsletter was queued: never send.
                $this->deliveries->markAttemptFailed($recipient->deliveryId, 'Not sent: the subscriber is no longer active.', null);
                $stats['failed']++;
                continue;
            }

            try {
                $this->channel->deliver($newsletter, $recipient);
                $this->deliveries->markSent($recipient->deliveryId);
                $stats['sent']++;
            } catch (MailException $e) {
                $this->recordFailure($recipient->deliveryId, $recipient->attempts + 1, $e->getMessage(), $maxAttempts, $backoff, $stats);
            } catch (Throwable $e) {
                $this->logger->error('Unexpected error delivering newsletter', ['delivery' => $recipient->deliveryId, 'error' => $e->getMessage()]);
                $this->recordFailure($recipient->deliveryId, $recipient->attempts + 1, 'Internal error: ' . $e->getMessage(), $maxAttempts, $backoff, $stats);
            }
        }
    }

    /**
     * @param list<int> $backoff
     * @param array<string, int|bool> $stats
     */
    private function recordFailure(int $deliveryId, int $attempt, string $error, int $maxAttempts, array $backoff, array &$stats): void
    {
        if ($attempt >= $maxAttempts) {
            $this->deliveries->markAttemptFailed($deliveryId, $error, null);
            $stats['failed']++;

            return;
        }
        $minutes = $backoff[min($attempt - 1, count($backoff) - 1)] ?? 15;
        $this->deliveries->markAttemptFailed($deliveryId, $error, new DateTimeImmutable("+{$minutes} minutes"));
        $stats['retrying']++;
    }

    private function finishCompleted(): int
    {
        $finished = 0;
        foreach ($this->newsletters->sendingIds() as $id) {
            $counts = $this->deliveries->counts($id);
            if ($counts->queued > 0) {
                continue;
            }
            $status = $counts->sent === 0 && $counts->failed > 0 ? NewsletterStatus::Failed : NewsletterStatus::Sent;
            $this->newsletters->markFinished($id, $status);
            $finished++;
        }

        return $finished;
    }
}
