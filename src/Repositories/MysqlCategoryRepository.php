<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Domain\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;

final class MysqlCategoryRepository implements CategoryRepositoryInterface
{
    private const ORDER = 'ORDER BY c.sort_order, c.name, c.id';

    public function __construct(private readonly Database $db)
    {
    }

    public function all(): array
    {
        return array_map($this->map(...), $this->db->fetchAll(
            'SELECT c.*, (SELECT count(*) FROM jobs j WHERE j.category_id = c.id AND j.deleted_at IS NULL) AS job_count
             FROM job_categories c ' . self::ORDER,
        ));
    }

    public function active(): array
    {
        return array_map($this->map(...), $this->db->fetchAll(
            "SELECT c.*, (SELECT count(*) FROM jobs j
                          WHERE j.category_id = c.id AND j.deleted_at IS NULL AND j.status = 'published'
                            AND (j.expires_at IS NULL OR j.expires_at > NOW(6))) AS job_count
             FROM job_categories c WHERE c.is_active = 1 " . self::ORDER,
        ));
    }

    public function findById(int $id): ?Category
    {
        $row = $this->db->fetchOne('SELECT c.*, 0 AS job_count FROM job_categories c WHERE c.id = ?', [$id]);

        return $row === null ? null : $this->map($row);
    }

    public function findBySlug(string $slug): ?Category
    {
        $row = $this->db->fetchOne('SELECT c.*, 0 AS job_count FROM job_categories c WHERE c.slug = ?', [$slug]);

        return $row === null ? null : $this->map($row);
    }

    public function findByName(string $name): ?Category
    {
        $row = $this->db->fetchOne('SELECT c.*, 0 AS job_count FROM job_categories c WHERE lower(c.name) = lower(?)', [$name]);

        return $row === null ? null : $this->map($row);
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        return (bool) $this->db->fetchValue(
            'SELECT EXISTS (SELECT 1 FROM job_categories WHERE slug = ? AND NOT (id <=> ?))',
            [$slug, $exceptId],
        );
    }

    public function create(string $name, string $slug, string $description, bool $isActive): Category
    {
        // MySQL cannot read the target table in a VALUES subquery, hence INSERT ... SELECT.
        $id = $this->db->insert(
            'INSERT INTO job_categories (name, slug, description, is_active, sort_order)
             SELECT ?, ?, ?, ?, coalesce(max(sort_order), 0) + 10 FROM job_categories',
            [$name, $slug, $description, $isActive],
        );

        return $this->findById($id) ?? throw new \RuntimeException('Category not found after insert.');
    }

    public function update(int $id, string $name, string $slug, string $description): void
    {
        $this->db->execute(
            'UPDATE job_categories SET name = ?, slug = ?, description = ? WHERE id = ?',
            [$name, $slug, $description, $id],
        );
    }

    public function setActive(int $id, bool $active): void
    {
        $this->db->execute('UPDATE job_categories SET is_active = ? WHERE id = ?', [$active, $id]);
    }

    public function reorder(array $orderedIds): void
    {
        $this->db->transaction(function (Database $db) use ($orderedIds): void {
            foreach (array_values($orderedIds) as $index => $id) {
                $db->execute('UPDATE job_categories SET sort_order = ? WHERE id = ?', [($index + 1) * 10, (int) $id]);
            }
        });
    }

    public function jobUsage(int $id): array
    {
        $row = $this->db->fetchOne(
            'SELECT coalesce(sum(deleted_at IS NULL), 0) AS live, coalesce(sum(deleted_at IS NOT NULL), 0) AS deleted
             FROM jobs WHERE category_id = ?',
            [$id],
        );

        return ['live' => (int) ($row['live'] ?? 0), 'deleted' => (int) ($row['deleted'] ?? 0)];
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM job_categories WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): Category
    {
        return new Category(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['slug'],
            (string) $row['description'],
            (int) $row['sort_order'],
            (bool) $row['is_active'],
            (int) $row['job_count'],
        );
    }
}
