<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\Actor;
use App\Domain\Paginated;
use App\Domain\Subscriber;

/** Back-office subscriber management. */
interface SubscriberAdminServiceInterface
{
    /**
     * @param array{q?: string, status?: string} $filters
     * @return Paginated<Subscriber>
     */
    public function search(array $filters, int $page, int $perPage): Paginated;

    /** @return array<string, int> */
    public function countByStatus(): array;

    public function unsubscribe(int $id, Actor $actor): void;

    /** Hard delete for privacy requests; the audit entry keeps only a masked address. */
    public function delete(int $id, Actor $actor): void;

    /** Writes the CSV export to $handle. */
    public function export(?string $status, $handle, Actor $actor): int;

    /**
     * @return array{imported: int, existing: int, duplicates_in_file: int, invalid: list<array{line: int, reason: string}>}
     * @throws \App\Domain\DomainException when consent was not confirmed
     */
    public function import(string $csv, bool $consentConfirmed, Actor $actor): array;
}
