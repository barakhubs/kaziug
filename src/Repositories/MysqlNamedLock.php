<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Repositories\Contracts\LockInterface;

/** MySQL named lock (GET_LOCK); held by the connection and released automatically if the process dies. */
final class MysqlNamedLock implements LockInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    public function tryAcquire(string $name): bool
    {
        return (int) $this->db->fetchValue('SELECT GET_LOCK(?, 0)', [self::lockName($name)]) === 1;
    }

    public function release(string $name): void
    {
        $this->db->fetchValue('SELECT RELEASE_LOCK(?)', [self::lockName($name)]);
    }

    /** Lock names are limited to 64 characters and shared by every database on the server, hence the prefix. */
    private static function lockName(string $name): string
    {
        return 'kaziug:' . substr(hash('sha256', $name), 0, 40);
    }
}
