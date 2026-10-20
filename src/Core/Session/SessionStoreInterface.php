<?php

declare(strict_types=1);

namespace App\Core\Session;

/**
 * Persists session payloads. Implementations receive a hash of the session id, never the
 * id itself, so a leaked store cannot be used to hijack sessions.
 */
interface SessionStoreInterface
{
    /** @return array<string, mixed>|null null when missing or idle for longer than $maxIdleSeconds */
    public function read(string $idHash, int $maxIdleSeconds): ?array;

    /** @param array<string, mixed> $data */
    public function write(string $idHash, array $data): void;

    public function destroy(string $idHash): void;

    /** Removes sessions idle for longer than $maxIdleSeconds. Returns the number removed. */
    public function gc(int $maxIdleSeconds): int;
}
