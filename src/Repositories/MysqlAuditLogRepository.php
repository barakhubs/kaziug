<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use InvalidArgumentException;

final class MysqlAuditLogRepository implements AuditLogRepositoryInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    public function insert(string $actor, string $action, string $entity, ?string $entityId, array $metadata, ?string $ip): void
    {
        $this->db->execute(
            'INSERT INTO audit_logs (actor, action, entity, entity_id, metadata, ip) VALUES (?, ?, ?, ?, ?, ?)',
            [$actor, $action, $entity, $entityId, json_encode($metadata, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), $ip],
        );
    }

    public function search(array $filters, int $limit, int $offset, string $direction = 'desc'): array
    {
        $dir = $direction === 'asc' ? 'ASC' : 'DESC';
        $where = [];
        $params = [];
        foreach (['action', 'entity', 'actor'] as $column) {
            if (($filters[$column] ?? '') !== '') {
                $where[] = "{$column} = ?";
                $params[] = $filters[$column];
            }
        }
        if (($filters['from'] ?? '') !== '') {
            $where[] = 'created_at >= CAST(? AS DATE)';
            $params[] = $filters['from'];
        }
        if (($filters['to'] ?? '') !== '') {
            $where[] = 'created_at < CAST(? AS DATE) + INTERVAL 1 DAY';
            $params[] = $filters['to'];
        }
        $whereSql = $where === [] ? '' : 'WHERE ' . implode(' AND ', $where);

        $total = (int) $this->db->fetchValue("SELECT count(*) FROM audit_logs {$whereSql}", $params);
        $rows = $this->db->fetchAll(
            "SELECT id, actor, action, entity, entity_id, metadata, ip, created_at
             FROM audit_logs {$whereSql} ORDER BY created_at {$dir}, id {$dir} LIMIT ? OFFSET ?",
            [...$params, $limit, $offset],
        );
        foreach ($rows as &$row) {
            $row['metadata'] = json_decode((string) $row['metadata'], true) ?: [];
        }

        return ['rows' => $rows, 'total' => $total];
    }

    public function distinct(string $column): array
    {
        if (!in_array($column, ['action', 'entity', 'actor'], true)) {
            throw new InvalidArgumentException("Cannot list distinct {$column}");
        }

        return array_column($this->db->fetchAll("SELECT DISTINCT {$column} AS v FROM audit_logs ORDER BY 1"), 'v');
    }
}
