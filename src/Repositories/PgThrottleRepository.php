<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Repositories\Contracts\ThrottleRepositoryInterface;
use DateTimeImmutable;

final class PgThrottleRepository implements ThrottleRepositoryInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    public function lockedUntil(string $key): ?DateTimeImmutable
    {
        $value = $this->db->fetchValue(
            'SELECT locked_until FROM login_attempts WHERE key = ? AND locked_until > now()',
            [$key],
        );

        return is_string($value) ? new DateTimeImmutable($value) : null;
    }

    public function recordFailure(string $key, int $windowSeconds): int
    {
        // Single statement, so concurrent failures cannot lose increments.
        return (int) $this->db->fetchValue(
            'INSERT INTO login_attempts (key, failure_count, window_started_at, updated_at)
             VALUES (:key, 1, now(), now())
             ON CONFLICT (key) DO UPDATE SET
                failure_count = CASE WHEN login_attempts.window_started_at < now() - make_interval(secs => :window_a)
                                     THEN 1 ELSE login_attempts.failure_count + 1 END,
                window_started_at = CASE WHEN login_attempts.window_started_at < now() - make_interval(secs => :window_b)
                                         THEN now() ELSE login_attempts.window_started_at END,
                updated_at = now()
             RETURNING failure_count',
            ['key' => $key, 'window_a' => $windowSeconds, 'window_b' => $windowSeconds],
        );
    }

    public function lock(string $key, int $seconds): void
    {
        $this->db->execute(
            'UPDATE login_attempts SET locked_until = now() + make_interval(secs => ?), updated_at = now() WHERE key = ?',
            [$seconds, $key],
        );
    }

    public function clear(string $key): void
    {
        $this->db->execute('DELETE FROM login_attempts WHERE key = ?', [$key]);
    }
}
