<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\Category;

interface CategoryRepositoryInterface
{
    /** @return list<Category> every category in display order, with counts of non-deleted jobs */
    public function all(): array;

    /** @return list<Category> active categories in display order, with counts of publicly visible jobs */
    public function active(): array;

    public function findById(int $id): ?Category;

    public function findBySlug(string $slug): ?Category;

    public function findByName(string $name): ?Category;

    public function slugExists(string $slug, ?int $exceptId = null): bool;

    public function create(string $name, string $slug, string $description, bool $isActive): Category;

    public function update(int $id, string $name, string $slug, string $description): void;

    public function setActive(int $id, bool $active): void;

    /** Stores the given id order as sort_order 10, 20, 30 ... */
    public function reorder(array $orderedIds): void;

    /** @return array{live: int, deleted: int} jobs referencing the category */
    public function jobUsage(int $id): array;

    public function delete(int $id): void;
}
