<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\Actor;

interface AuditServiceInterface
{
    /** @param array<string, mixed> $metadata must never contain secrets */
    public function record(Actor $actor, string $action, string $entity, int|string|null $entityId = null, array $metadata = []): void;

    /**
     * @param array{action?: string, entity?: string, actor?: string, from?: string, to?: string} $filters
     * @return array{rows: list<array<string, mixed>>, total: int}
     */
    public function search(array $filters, int $page, int $perPage): array;

    /** @return list<array<string, mixed>> */
    public function recent(int $limit): array;

    /** @return array{actions: list<string>, entities: list<string>, actors: list<string>} */
    public function filterOptions(): array;
}
