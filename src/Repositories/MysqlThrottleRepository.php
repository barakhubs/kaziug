<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Core\Database\DbTime;
use App\Repositories\Contracts\ThrottleRepositoryInterface;
use DateTimeImmutable;

final class MysqlThrottleRepository implements ThrottleRepositoryInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    public function lockedUntil(string $key): ?DateTimeImmutable
    {
        $value = $this->db->fetchValue(
            'SELECT locked_until FROM login_attempts WHERE `key` = ? AND locked_until > NOW(6)',
            [$key],
        );

        return DbTime::fromDb($value);
    }

    public function recordFailure(string $key, int $windowSeconds): int
    {
        // A single upsert, so concurrent failures cannot lose increments. MySQL applies the SET list left
        // to right, so failure_count is computed before window_started_at is reset.
        $this->db->execute(
            'INSERT INTO login_attempts (`key`, failure_count, window_started_at, updated_at)
             VALUES (:key, 1, NOW(6), NOW(6))
             ON DUPLICATE KEY UPDATE
                failure_count = CASE WHEN window_started_at < NOW(6) - INTERVAL :window_a SECOND
                                     THEN 1 ELSE failure_count + 1 END,
                window_started_at = CASE WHEN window_started_at < NOW(6) - INTERVAL :window_b SECOND
                                         THEN NOW(6) ELSE window_started_at END,
                updated_at = NOW(6)',
            ['key' => $key, 'window_a' => $windowSeconds, 'window_b' => $windowSeconds],
        );

        return (int) $this->db->fetchValue('SELECT failure_count FROM login_attempts WHERE `key` = ?', [$key]);
    }

    public function lock(string $key, int $seconds): void
    {
        $this->db->execute(
            'UPDATE login_attempts SET locked_until = NOW(6) + INTERVAL ? SECOND, updated_at = NOW(6) WHERE `key` = ?',
            [$seconds, $key],
        );
    }

    public function clear(string $key): void
    {
        $this->db->execute('DELETE FROM login_attempts WHERE `key` = ?', [$key]);
    }

    public function purgeOlderThan(int $seconds): int
    {
        return $this->db->execute(
            'DELETE FROM login_attempts WHERE updated_at < NOW(6) - INTERVAL ? SECOND
               AND (locked_until IS NULL OR locked_until < NOW(6))',
            [$seconds],
        );
    }
}
