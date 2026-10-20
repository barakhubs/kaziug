<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Domain\DeliveryCounts;
use App\Domain\Recipient;
use App\Repositories\Contracts\DeliveryRepositoryInterface;
use DateTimeImmutable;

final class PgDeliveryRepository implements DeliveryRepositoryInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    public function queueForActiveSubscribers(int $newsletterId): int
    {
        return $this->db->execute(
            "INSERT INTO newsletter_deliveries (newsletter_id, subscriber_id)
             SELECT ?, id FROM subscribers WHERE status = 'active'
             ON CONFLICT (newsletter_id, subscriber_id) DO NOTHING",
            [$newsletterId],
        );
    }

    public function claimDue(int $limit, int $leaseSeconds): array
    {
        if ($limit < 1) {
            return [];
        }

        // SKIP LOCKED lets several workers run side by side without sending anything twice.
        $rows = $this->db->fetchAll(
            "WITH due AS (
                SELECT d.id FROM newsletter_deliveries d
                JOIN newsletters n ON n.id = d.newsletter_id AND n.status = 'sending'
                WHERE d.status = 'queued' AND d.next_attempt_at <= now()
                ORDER BY d.next_attempt_at, d.id
                LIMIT ?
                FOR UPDATE OF d SKIP LOCKED
             )
             UPDATE newsletter_deliveries d SET next_attempt_at = now() + make_interval(secs => ?)
             FROM due, subscribers s
             WHERE d.id = due.id AND s.id = d.subscriber_id
             RETURNING d.id, d.newsletter_id, d.subscriber_id, d.attempts, s.email, s.name, s.unsubscribe_token,
                       (s.status = 'active') AS still_active",
            [$limit, $leaseSeconds],
        );
        usort($rows, static fn (array $a, array $b): int => (int) $a['id'] <=> (int) $b['id']);

        return array_map(static fn (array $r): Recipient => new Recipient(
            (int) $r['id'],
            (int) $r['newsletter_id'],
            (int) $r['subscriber_id'],
            (string) $r['email'],
            $r['name'] === null ? null : (string) $r['name'],
            (string) $r['unsubscribe_token'],
            (bool) $r['still_active'],
            (int) $r['attempts'],
        ), $rows);
    }

    public function sentInLast(int $seconds): int
    {
        return (int) $this->db->fetchValue(
            "SELECT count(*) FROM newsletter_deliveries WHERE status = 'sent' AND sent_at > now() - make_interval(secs => ?)",
            [$seconds],
        );
    }

    public function markSent(int $deliveryId): void
    {
        $this->db->execute(
            "UPDATE newsletter_deliveries SET status = 'sent', sent_at = now(), attempts = attempts + 1, last_error = NULL WHERE id = ?",
            [$deliveryId],
        );
    }

    public function markAttemptFailed(int $deliveryId, string $error, ?DateTimeImmutable $retryAt): void
    {
        $this->db->execute(
            "UPDATE newsletter_deliveries SET attempts = attempts + 1, last_error = ?,
                status = CASE WHEN ?::timestamptz IS NULL THEN 'failed' ELSE 'queued' END,
                next_attempt_at = coalesce(?::timestamptz, next_attempt_at)
             WHERE id = ?",
            [mb_substr($error, 0, 1000), $retryAt?->format(DATE_ATOM), $retryAt?->format(DATE_ATOM), $deliveryId],
        );
    }

    public function counts(int $newsletterId): DeliveryCounts
    {
        $row = $this->db->fetchOne(
            "SELECT count(*) FILTER (WHERE status = 'queued') AS queued, count(*) FILTER (WHERE status = 'sent') AS sent,
                    count(*) FILTER (WHERE status = 'failed') AS failed
             FROM newsletter_deliveries WHERE newsletter_id = ?",
            [$newsletterId],
        );

        return new DeliveryCounts((int) ($row['queued'] ?? 0), (int) ($row['sent'] ?? 0), (int) ($row['failed'] ?? 0));
    }

    public function permanentFailures(int $newsletterId, int $limit = 200): array
    {
        return array_map(static fn (array $r): array => [
            'email' => (string) $r['email'],
            'attempts' => (int) $r['attempts'],
            'last_error' => $r['last_error'] === null ? null : (string) $r['last_error'],
        ], $this->db->fetchAll(
            "SELECT s.email, d.attempts, d.last_error FROM newsletter_deliveries d JOIN subscribers s ON s.id = d.subscriber_id
             WHERE d.newsletter_id = ? AND d.status = 'failed' ORDER BY d.id LIMIT ?",
            [$newsletterId, $limit],
        ));
    }
}
