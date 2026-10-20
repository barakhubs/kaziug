<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\Actor;
use App\Domain\Category;

interface CategoryServiceInterface
{
    /** @return list<Category> */
    public function all(): array;

    /** @return list<Category> */
    public function active(): array;

    public function get(int $id): Category;

    public function findActiveBySlug(string $slug): ?Category;

    /** @param array{name: string, slug: ?string, description: ?string, is_active: bool} $data */
    public function create(array $data, Actor $actor): Category;

    /** @param array{name: string, slug: ?string, description: ?string} $data */
    public function update(int $id, array $data, Actor $actor): void;

    public function setActive(int $id, bool $active, Actor $actor): void;

    /** @param 'up'|'down' $direction */
    public function move(int $id, string $direction, Actor $actor): void;

    /**
     * Deleting a category that still has jobs is blocked (deleted jobs included, because
     * they still reference it). Deactivate it instead.
     * @throws \App\Domain\DomainException
     */
    public function delete(int $id, Actor $actor): void;

    /** Finds a category by name or creates it (used by job import sources). */
    public function ensure(string $name, Actor $actor): Category;
}
