<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Actor;
use App\Services\Contracts\AuditServiceInterface;

final class SpyAuditService implements AuditServiceInterface
{
    /** @var list<array{actor: string, action: string, entity: string, id: int|string|null, metadata: array<string, mixed>}> */
    public array $records = [];

    public function record(Actor $actor, string $action, string $entity, int|string|null $entityId = null, array $metadata = []): void
    {
        $this->records[] = ['actor' => $actor->label, 'action' => $action, 'entity' => $entity, 'id' => $entityId, 'metadata' => $metadata];
    }

    /** @return list<string> */
    public function actions(): array
    {
        return array_column($this->records, 'action');
    }

    public function search(array $filters, int $page, int $perPage): array
    {
        return ['rows' => [], 'total' => 0];
    }

    public function recent(int $limit): array
    {
        return [];
    }

    public function filterOptions(): array
    {
        return ['actions' => [], 'entities' => [], 'actors' => []];
    }
}
