<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface AuditLogRepositoryInterface
{
    /** @param array<string, mixed> $metadata */
    public function insert(string $actor, string $action, string $entity, ?string $entityId, array $metadata, ?string $ip): void;

    /**
     * @param array{action?: string, entity?: string, actor?: string, from?: string, to?: string} $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function search(array $filters, int $limit, int $offset, string $direction = 'desc'): array;

    /** @return list<string> distinct values of a column, for filter dropdowns */
    public function distinct(string $column): array;
}
