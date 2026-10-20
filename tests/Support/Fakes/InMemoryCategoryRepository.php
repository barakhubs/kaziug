<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Category;
use App\Repositories\Contracts\CategoryRepositoryInterface;

final class InMemoryCategoryRepository implements CategoryRepositoryInterface
{
    /** @var array<int, Category> */
    public array $categories = [];

    /** @var array<int, array{live: int, deleted: int}> */
    public array $usage = [];

    private int $nextId = 1;

    public function all(): array
    {
        $all = array_values($this->categories);
        usort($all, static fn (Category $a, Category $b): int => [$a->sortOrder, $a->id] <=> [$b->sortOrder, $b->id]);

        return $all;
    }

    public function active(): array
    {
        return array_values(array_filter($this->all(), static fn (Category $c): bool => $c->isActive));
    }

    public function findById(int $id): ?Category
    {
        return $this->categories[$id] ?? null;
    }

    public function findBySlug(string $slug): ?Category
    {
        foreach ($this->categories as $c) {
            if ($c->slug === $slug) {
                return $c;
            }
        }

        return null;
    }

    public function findByName(string $name): ?Category
    {
        foreach ($this->categories as $c) {
            if (strcasecmp($c->name, $name) === 0) {
                return $c;
            }
        }

        return null;
    }

    public function slugExists(string $slug, ?int $exceptId = null): bool
    {
        $found = $this->findBySlug($slug);

        return $found !== null && $found->id !== $exceptId;
    }

    public function create(string $name, string $slug, string $description, bool $isActive): Category
    {
        $id = $this->nextId++;

        return $this->categories[$id] = new Category($id, $name, $slug, $description, $id * 10, $isActive);
    }

    public function update(int $id, string $name, string $slug, string $description): void
    {
        $c = $this->categories[$id];
        $this->categories[$id] = new Category($id, $name, $slug, $description, $c->sortOrder, $c->isActive);
    }

    public function setActive(int $id, bool $active): void
    {
        $c = $this->categories[$id];
        $this->categories[$id] = new Category($id, $c->name, $c->slug, $c->description, $c->sortOrder, $active);
    }

    public function reorder(array $orderedIds): void
    {
        foreach (array_values($orderedIds) as $i => $id) {
            $c = $this->categories[$id];
            $this->categories[$id] = new Category($id, $c->name, $c->slug, $c->description, ($i + 1) * 10, $c->isActive);
        }
    }

    public function jobUsage(int $id): array
    {
        return $this->usage[$id] ?? ['live' => 0, 'deleted' => 0];
    }

    public function delete(int $id): void
    {
        unset($this->categories[$id]);
    }
}
