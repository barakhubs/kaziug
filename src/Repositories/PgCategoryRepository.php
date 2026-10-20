<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Domain\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;

final class PgCategoryRepository implements CategoryRepositoryInterface
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
                            AND (j.expires_at IS NULL OR j.expires_at > now())) AS job_count
             FROM job_categories c WHERE c.is_active " . self::ORDER,
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
            'SELECT EXISTS (SELECT 1 FROM job_categories WHERE slug = ? AND id IS DISTINCT FROM ?)',
            [$slug, $exceptId],
        );
    }

    public function create(string $name, string $slug, string $description, bool $isActive): Category
    {
        $row = $this->db->fetchOne(
            'INSERT INTO job_categories (name, slug, description, is_active, sort_order)
             VALUES (?, ?, ?, ?, (SELECT coalesce(max(sort_order), 0) + 10 FROM job_categories))
             RETURNING *, 0 AS job_count',
            [$name, $slug, $description, $isActive],
        );

        return $this->map($row ?? []);
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
            'SELECT count(*) FILTER (WHERE deleted_at IS NULL) AS live, count(*) FILTER (WHERE deleted_at IS NOT NULL) AS deleted
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
