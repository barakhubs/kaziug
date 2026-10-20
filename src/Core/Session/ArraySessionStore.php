<?php

declare(strict_types=1);

namespace App\Core\Session;

/**
 * In-memory store for tests.
 */
final class ArraySessionStore implements SessionStoreInterface
{
    /** @var array<string, array{data: array<string, mixed>, at: int}> */
    public array $sessions = [];

    public function read(string $idHash, int $maxIdleSeconds): ?array
    {
        $row = $this->sessions[$idHash] ?? null;
        if ($row === null || $row['at'] < time() - $maxIdleSeconds) {
            return null;
        }

        return $row['data'];
    }

    public function write(string $idHash, array $data): void
    {
        $this->sessions[$idHash] = ['data' => $data, 'at' => time()];
    }

    public function destroy(string $idHash): void
    {
        unset($this->sessions[$idHash]);
    }

    public function gc(int $maxIdleSeconds): int
    {
        $before = count($this->sessions);
        $this->sessions = array_filter($this->sessions, static fn (array $row): bool => $row['at'] >= time() - $maxIdleSeconds);

        return $before - count($this->sessions);
    }
}
