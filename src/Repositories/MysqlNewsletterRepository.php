<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Core\Database\DbTime;
use App\Domain\Newsletter;
use App\Domain\NewsletterStatus;
use App\Repositories\Contracts\NewsletterRepositoryInterface;
use DateTimeImmutable;

final class MysqlNewsletterRepository implements NewsletterRepositoryInterface
{
    private const SELECT = 'SELECT n.*, a.email AS created_by_email FROM newsletters n LEFT JOIN admin_users a ON a.id = n.created_by';

    public function __construct(private readonly Database $db)
    {
    }

    public function findById(int $id): ?Newsletter
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE n.id = ?', [$id]);

        return $row === null ? null : $this->map($row);
    }

    public function paginate(int $limit, int $offset, string $q = '', string $sort = 'created', string $direction = 'desc'): array
    {
        $dir = $direction === 'asc' ? 'ASC' : 'DESC';
        $order = match ($sort) {
            'subject' => "lower(n.subject) {$dir}",
            'status' => "n.status {$dir}, n.created_at DESC",
            default => "n.created_at {$dir}",
        };
        $where = '';
        $params = [];
        if (trim($q) !== '') {
            $where = 'WHERE n.subject LIKE ?';
            $params[] = '%' . addcslashes(trim($q), '%_\\') . '%';
        }

        return [
            'items' => array_map($this->map(...), $this->db->fetchAll(
                self::SELECT . " {$where} ORDER BY {$order}, n.id DESC LIMIT ? OFFSET ?",
                [...$params, $limit, $offset],
            )),
            'total' => (int) $this->db->fetchValue("SELECT count(*) FROM newsletters n {$where}", $params),
        ];
    }

    public function latest(): ?Newsletter
    {
        $row = $this->db->fetchOne(self::SELECT . ' ORDER BY coalesce(n.sent_at, n.scheduled_at, n.created_at) DESC, n.id DESC LIMIT 1');

        return $row === null ? null : $this->map($row);
    }

    public function create(array $data, ?int $adminId): int
    {
        return $this->db->insert(
            'INSERT INTO newsletters (subject, preheader, body_html, body_text, created_by) VALUES (?, ?, ?, ?, ?)',
            [$data['subject'], $data['preheader'], $data['body_html'], $data['body_text'], $adminId],
        );
    }

    public function update(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE newsletters SET subject = ?, preheader = ?, body_html = ?, body_text = ? WHERE id = ?',
            [$data['subject'], $data['preheader'], $data['body_html'], $data['body_text'], $id],
        );
    }

    public function transition(int $id, array $from, NewsletterStatus $to, ?DateTimeImmutable $scheduledAt = null): bool
    {
        $placeholders = implode(', ', array_fill(0, count($from), '?'));

        // Scheduling sets the time, going back to draft clears it, anything else keeps it (for history).
        return $this->db->execute(
            "UPDATE newsletters SET status = ?,
                scheduled_at = CASE ? WHEN 'scheduled' THEN CAST(? AS DATETIME(6)) WHEN 'draft' THEN NULL ELSE scheduled_at END
             WHERE id = ? AND status IN ({$placeholders})",
            [$to->value, $to->value, DbTime::toDb($scheduledAt), $id, ...array_map(static fn (NewsletterStatus $s): string => $s->value, $from)],
        ) === 1;
    }

    public function dueScheduledIds(DateTimeImmutable $now): array
    {
        return array_map('intval', array_column($this->db->fetchAll(
            "SELECT id FROM newsletters WHERE status = 'scheduled' AND scheduled_at <= ? ORDER BY scheduled_at",
            [DbTime::toDb($now)],
        ), 'id'));
    }

    public function sendingIds(): array
    {
        return array_map('intval', array_column($this->db->fetchAll("SELECT id FROM newsletters WHERE status = 'sending'"), 'id'));
    }

    public function markFinished(int $id, NewsletterStatus $status): void
    {
        $this->db->execute(
            "UPDATE newsletters SET status = ?, sent_at = NOW(6) WHERE id = ? AND status = 'sending'",
            [$status->value, $id],
        );
    }

    public function delete(int $id): void
    {
        $this->db->execute("DELETE FROM newsletters WHERE id = ? AND status = 'draft'", [$id]);
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): Newsletter
    {
        return new Newsletter(
            (int) $row['id'],
            (string) $row['subject'],
            (string) $row['preheader'],
            (string) $row['body_html'],
            (string) $row['body_text'],
            NewsletterStatus::from((string) $row['status']),
            JobRowMapper::date($row['scheduled_at']),
            JobRowMapper::date($row['sent_at']),
            $row['created_by_email'] === null ? null : (string) $row['created_by_email'],
            JobRowMapper::date($row['created_at']) ?? new DateTimeImmutable(),
            JobRowMapper::date($row['updated_at']) ?? new DateTimeImmutable(),
        );
    }
}
