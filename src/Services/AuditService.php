<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Actor;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Services\Contracts\AuditServiceInterface;

final class AuditService implements AuditServiceInterface
{
    public function __construct(private readonly AuditLogRepositoryInterface $logs)
    {
    }

    public function record(Actor $actor, string $action, string $entity, int|string|null $entityId = null, array $metadata = []): void
    {
        $this->logs->insert(
            $actor->label,
            $action,
            $entity,
            $entityId === null ? null : (string) $entityId,
            $metadata,
            $actor->ip !== null && filter_var($actor->ip, FILTER_VALIDATE_IP) !== false ? $actor->ip : null,
        );
    }

    public function search(array $filters, int $page, int $perPage, string $direction = 'desc'): array
    {
        return $this->logs->search($filters, $perPage, max(0, ($page - 1) * $perPage), $direction);
    }

    public function recent(int $limit): array
    {
        return $this->logs->search([], $limit, 0)['rows'];
    }

    public function filterOptions(): array
    {
        return [
            'actions' => $this->logs->distinct('action'),
            'entities' => $this->logs->distinct('entity'),
            'actors' => $this->logs->distinct('actor'),
        ];
    }
}
