<?php

declare(strict_types=1);

namespace App\Core\Session;

use App\Core\Database\Database;

/**
 * Database-backed sessions so they survive redeploys and work across several app instances.
 */
final class PgSessionStore implements SessionStoreInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    public function read(string $idHash, int $maxIdleSeconds): ?array
    {
        $payload = $this->db->fetchValue(
            "SELECT payload FROM sessions WHERE id = ? AND last_activity > now() - make_interval(secs => ?)",
            [$idHash, $maxIdleSeconds],
        );
        if (!is_string($payload)) {
            return null;
        }
        $data = json_decode($payload, true);

        return is_array($data) ? $data : null;
    }

    public function write(string $idHash, array $data): void
    {
        $this->db->execute(
            'INSERT INTO sessions (id, payload, last_activity) VALUES (?, ?, now())
             ON CONFLICT (id) DO UPDATE SET payload = EXCLUDED.payload, last_activity = now()',
            [$idHash, json_encode($data, JSON_THROW_ON_ERROR)],
        );
    }

    public function destroy(string $idHash): void
    {
        $this->db->execute('DELETE FROM sessions WHERE id = ?', [$idHash]);
    }

    public function gc(int $maxIdleSeconds): int
    {
        return $this->db->execute(
            'DELETE FROM sessions WHERE last_activity < now() - make_interval(secs => ?)',
            [$maxIdleSeconds],
        );
    }
}
