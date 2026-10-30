<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Core\Database\DbTime;
use App\Domain\Subscriber;
use App\Domain\SubscriberStatus;
use App\Repositories\Contracts\SubscriberRepositoryInterface;

final class MysqlSubscriberRepository implements SubscriberRepositoryInterface
{
    private const COLUMNS = 'id, email, name, status, consent_at, consent_ip, confirm_sent_at,
        confirmed_at, unsubscribe_token, created_at, unsubscribed_at';

    public function __construct(private readonly Database $db)
    {
    }

    public function findById(int $id): ?Subscriber
    {
        return $this->one('WHERE id = ?', [$id]);
    }

    public function findByEmail(string $email): ?Subscriber
    {
        return $this->one('WHERE email = lower(?)', [$email]);
    }

    public function findPendingByConfirmHash(string $hash, int $maxAgeSeconds): ?Subscriber
    {
        return $this->one(
            "WHERE confirm_token_hash = ? AND status = 'pending' AND confirm_sent_at > NOW(6) - INTERVAL ? SECOND",
            [$hash, $maxAgeSeconds],
        );
    }

    public function findByUnsubscribeToken(string $token): ?Subscriber
    {
        return $this->one('WHERE unsubscribe_token = ?', [$token]);
    }

    public function createPending(string $email, ?string $name, string $consentIp, string $confirmHash, string $unsubscribeToken): Subscriber
    {
        $id = $this->db->insert(
            "INSERT INTO subscribers (email, name, status, consent_at, consent_ip, confirm_token_hash, confirm_sent_at, unsubscribe_token)
             VALUES (lower(?), ?, 'pending', NOW(6), ?, ?, NOW(6), ?)",
            [$email, $name, $consentIp, $confirmHash, $unsubscribeToken],
        );

        return $this->findById($id) ?? throw new \RuntimeException('Subscriber not found after insert.');
    }

    public function restartConfirmation(int $id, ?string $name, string $consentIp, string $confirmHash): void
    {
        $this->db->execute(
            "UPDATE subscribers SET status = 'pending', name = coalesce(?, name), consent_at = NOW(6), consent_ip = ?,
                confirm_token_hash = ?, confirm_sent_at = NOW(6), confirmed_at = NULL, unsubscribed_at = NULL
             WHERE id = ?",
            [$name, $consentIp, $confirmHash, $id],
        );
    }

    public function activate(int $id): void
    {
        $this->db->execute(
            "UPDATE subscribers SET status = 'active', confirmed_at = NOW(6), confirm_token_hash = NULL WHERE id = ?",
            [$id],
        );
    }

    public function unsubscribe(int $id): void
    {
        $this->db->execute(
            "UPDATE subscribers SET status = 'unsubscribed', unsubscribed_at = coalesce(unsubscribed_at, NOW(6)),
                confirm_token_hash = NULL WHERE id = ?",
            [$id],
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM subscribers WHERE id = ?', [$id]);
    }

    public function search(array $filters, int $limit, int $offset, string $sort = 'joined', string $direction = 'desc'): array
    {
        $dir = $direction === 'asc' ? 'ASC' : 'DESC';
        $order = match ($sort) {
            'email' => "lower(email) {$dir}",
            'status' => "status {$dir}, created_at DESC",
            default => "created_at {$dir}",
        };
        $where = [];
        $params = [];
        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(email LIKE ? OR name LIKE ?)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            array_push($params, $like, $like);
        }
        if (($filters['status'] ?? '') !== '') {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $total = (int) $this->db->fetchValue("SELECT count(*) FROM subscribers {$whereSql}", $params);
        $rows = $this->db->fetchAll(
            'SELECT ' . self::COLUMNS . " FROM subscribers {$whereSql} ORDER BY {$order}, id DESC LIMIT ? OFFSET ?",
            [...$params, $limit, $offset],
        );

        return ['items' => array_map($this->map(...), $rows), 'total' => $total];
    }

    public function countByStatus(): array
    {
        $counts = array_fill_keys(SubscriberStatus::values(), 0);
        foreach ($this->db->fetchAll('SELECT status, count(*) AS n FROM subscribers GROUP BY status') as $row) {
            $counts[(string) $row['status']] = (int) $row['n'];
        }

        return $counts;
    }

    public function activityBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        // Imported rows have no consent_ip: they joined outside the site and were never asked to confirm.
        $row = $this->db->fetchOne(
            'SELECT coalesce(sum(consent_ip IS NOT NULL AND created_at >= r.a AND created_at < r.b), 0) AS signups,
                    coalesce(sum(consent_ip IS NOT NULL AND confirmed_at >= r.a AND confirmed_at < r.b), 0) AS confirmations,
                    coalesce(sum(unsubscribed_at >= r.a AND unsubscribed_at < r.b), 0) AS unsubscribes
             FROM subscribers, (SELECT CAST(? AS DATETIME(6)) AS a, CAST(? AS DATETIME(6)) AS b) AS r',
            [DbTime::toDb($from), DbTime::toDb($to)],
        ) ?? [];

        return [
            'signups' => (int) ($row['signups'] ?? 0),
            'confirmations' => (int) ($row['confirmations'] ?? 0),
            'unsubscribes' => (int) ($row['unsubscribes'] ?? 0),
        ];
    }

    public function each(?string $status): iterable
    {
        // Keyset pagination keeps memory flat for large exports.
        $lastId = 0;
        do {
            $rows = $this->db->fetchAll(
                'SELECT ' . self::COLUMNS . ' FROM subscribers WHERE id > ? AND (? IS NULL OR status = ?) ORDER BY id LIMIT 500',
                [$lastId, $status, $status],
            );
            foreach ($rows as $row) {
                $lastId = (int) $row['id'];
                yield $this->map($row);
            }
        } while (count($rows) === 500);
    }

    public function existingEmails(array $emails): array
    {
        if ($emails === []) {
            return [];
        }
        $found = [];
        foreach (array_chunk($emails, 1000) as $chunk) {
            $placeholders = implode(', ', array_fill(0, count($chunk), '?'));
            foreach ($this->db->fetchAll("SELECT email AS e FROM subscribers WHERE email IN ({$placeholders})", array_map('strtolower', $chunk)) as $row) {
                $found[] = (string) $row['e'];
            }
        }

        return $found;
    }

    public function insertImported(array $rows): int
    {
        return $this->db->transaction(function (Database $db) use ($rows): int {
            $inserted = 0;
            foreach ($rows as $row) {
                $inserted += $db->execute(
                    "INSERT INTO subscribers (email, name, status, consent_at, confirmed_at, unsubscribe_token)
                     SELECT lower(?), ?, 'active', NOW(6), NOW(6), ? FROM DUAL
                     WHERE NOT EXISTS (SELECT 1 FROM subscribers WHERE email = lower(?))",
                    [$row['email'], $row['name'], $row['unsubscribe_token'], $row['email']],
                );
            }

            return $inserted;
        });
    }

    /** @param list<mixed> $params */
    private function one(string $where, array $params): ?Subscriber
    {
        $row = $this->db->fetchOne('SELECT ' . self::COLUMNS . " FROM subscribers {$where}", $params);

        return $row === null ? null : $this->map($row);
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): Subscriber
    {
        return new Subscriber(
            (int) $row['id'],
            (string) $row['email'],
            $row['name'] === null ? null : (string) $row['name'],
            SubscriberStatus::from((string) $row['status']),
            JobRowMapper::date($row['consent_at']),
            $row['consent_ip'] === null ? null : (string) $row['consent_ip'],
            JobRowMapper::date($row['confirm_sent_at']),
            JobRowMapper::date($row['confirmed_at']),
            (string) $row['unsubscribe_token'],
            JobRowMapper::date($row['created_at']) ?? new \DateTimeImmutable(),
            JobRowMapper::date($row['unsubscribed_at']),
        );
    }
}
