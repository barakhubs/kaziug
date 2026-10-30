<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Core\Database\DbTime;
use App\Domain\DeliveryCounts;
use App\Domain\Recipient;
use App\Repositories\Contracts\DeliveryRepositoryInterface;
use DateTimeImmutable;

final class MysqlDeliveryRepository implements DeliveryRepositoryInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    public function queueForActiveSubscribers(int $newsletterId): int
    {
        return $this->db->execute(
            "INSERT INTO newsletter_deliveries (newsletter_id, subscriber_id)
             SELECT ?, s.id FROM subscribers s WHERE s.status = 'active'
               AND NOT EXISTS (SELECT 1 FROM newsletter_deliveries d WHERE d.newsletter_id = ? AND d.subscriber_id = s.id)",
            [$newsletterId, $newsletterId],
        );
    }

    public function claimDue(int $limit, int $leaseSeconds): array
    {
        if ($limit < 1) {
            return [];
        }

        // SKIP LOCKED lets several workers run side by side without sending anything twice. The newsletter
        // check sits in a subquery so only delivery rows are locked, not the newsletter itself.
        $rows = $this->db->transaction(function (Database $db) use ($limit, $leaseSeconds): array {
            $ids = array_map('intval', array_column($db->fetchAll(
                "SELECT d.id FROM newsletter_deliveries d
                 WHERE d.status = 'queued' AND d.next_attempt_at <= NOW(6)
                   AND d.newsletter_id IN (SELECT n.id FROM newsletters n WHERE n.status = 'sending')
                 ORDER BY d.next_attempt_at, d.id
                 LIMIT ?
                 FOR UPDATE SKIP LOCKED",
                [$limit],
            ), 'id'));
            if ($ids === []) {
                return [];
            }
            $in = implode(', ', array_fill(0, count($ids), '?'));
            $db->execute(
                "UPDATE newsletter_deliveries SET next_attempt_at = NOW(6) + INTERVAL ? SECOND WHERE id IN ({$in})",
                [$leaseSeconds, ...$ids],
            );

            return $db->fetchAll(
                "SELECT d.id, d.newsletter_id, d.subscriber_id, d.attempts, s.email, s.name, s.unsubscribe_token,
                        (s.status = 'active') AS still_active
                 FROM newsletter_deliveries d JOIN subscribers s ON s.id = d.subscriber_id
                 WHERE d.id IN ({$in}) ORDER BY d.id",
                $ids,
            );
        });

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
            "SELECT count(*) FROM newsletter_deliveries WHERE status = 'sent' AND sent_at > NOW(6) - INTERVAL ? SECOND",
            [$seconds],
        );
    }

    public function markSent(int $deliveryId): void
    {
        $this->db->execute(
            "UPDATE newsletter_deliveries SET status = 'sent', sent_at = NOW(6), attempts = attempts + 1, last_error = NULL WHERE id = ?",
            [$deliveryId],
        );
    }

    public function markAttemptFailed(int $deliveryId, string $error, ?DateTimeImmutable $retryAt): void
    {
        $this->db->execute(
            "UPDATE newsletter_deliveries SET attempts = attempts + 1, last_error = ?,
                status = CASE WHEN ? IS NULL THEN 'failed' ELSE 'queued' END,
                next_attempt_at = coalesce(CAST(? AS DATETIME(6)), next_attempt_at)
             WHERE id = ?",
            [mb_substr($error, 0, 1000), DbTime::toDb($retryAt), DbTime::toDb($retryAt), $deliveryId],
        );
    }

    public function counts(int $newsletterId): DeliveryCounts
    {
        $row = $this->db->fetchOne(
            "SELECT coalesce(sum(status = 'queued'), 0) AS queued, coalesce(sum(status = 'sent'), 0) AS sent,
                    coalesce(sum(status = 'failed'), 0) AS failed
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
