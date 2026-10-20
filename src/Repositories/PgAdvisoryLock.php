<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Repositories\Contracts\LockInterface;

/** PostgreSQL session-level advisory lock; released automatically if the process dies. */
final class PgAdvisoryLock implements LockInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    public function tryAcquire(string $name): bool
    {
        return (bool) $this->db->fetchValue('SELECT pg_try_advisory_lock(hashtext(?))', [$name]);
    }

    public function release(string $name): void
    {
        $this->db->fetchValue('SELECT pg_advisory_unlock(hashtext(?))', [$name]);
    }
}
