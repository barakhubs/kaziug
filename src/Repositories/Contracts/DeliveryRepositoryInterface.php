<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\DeliveryCounts;
use App\Domain\Recipient;
use DateTimeImmutable;

/** newsletter_deliveries: the send queue. */
interface DeliveryRepositoryInterface
{
    /** Queues one delivery per currently active subscriber. Idempotent. Returns rows added. */
    public function queueForActiveSubscribers(int $newsletterId): int;

    /**
     * Claims up to $limit queued deliveries that are due, oldest first, and pushes their
     * next_attempt_at forward by $leaseSeconds so an overlapping worker won't pick them up.
     * @return list<Recipient>
     */
    public function claimDue(int $limit, int $leaseSeconds): array;

    /** Deliveries sent in the last $seconds (for the per-minute rate limit). */
    public function sentInLast(int $seconds): int;

    public function markSent(int $deliveryId): void;

    /** Records a failed attempt: retried at $retryAt, or failed for good when $retryAt is null. */
    public function markAttemptFailed(int $deliveryId, string $error, ?DateTimeImmutable $retryAt): void;

    public function counts(int $newsletterId): DeliveryCounts;

    /** @return list<array{email: string, attempts: int, last_error: ?string}> */
    public function permanentFailures(int $newsletterId, int $limit = 200): array;
}
