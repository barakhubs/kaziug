<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Actor;
use App\Domain\Category;
use App\Domain\DomainException;
use App\Domain\NotFoundException;
use App\Domain\Slug;
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Services\Contracts\AuditServiceInterface;
use App\Services\Contracts\CategoryServiceInterface;

final class CategoryService implements CategoryServiceInterface
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categories,
        private readonly AuditServiceInterface $audit,
    ) {
    }

    public function all(): array
    {
        return $this->categories->all();
    }

    public function active(): array
    {
        return $this->categories->active();
    }

    public function get(int $id): Category
    {
        return $this->categories->findById($id) ?? throw new NotFoundException('Category not found.');
    }

    public function findActiveBySlug(string $slug): ?Category
    {
        $category = $this->categories->findBySlug($slug);

        return $category !== null && $category->isActive ? $category : null;
    }

    public function create(array $data, Actor $actor): Category
    {
        $slug = $this->resolveSlug($data['slug'] ?? null, $data['name'], null);
        $category = $this->categories->create($data['name'], $slug, (string) ($data['description'] ?? ''), $data['is_active']);
        $this->audit->record($actor, 'category.created', 'category', $category->id, ['name' => $category->name]);

        return $category;
    }

    public function update(int $id, array $data, Actor $actor): void
    {
        $current = $this->get($id);
        $slug = $this->resolveSlug($data['slug'] ?? null, $data['name'], $id);
        $this->categories->update($id, $data['name'], $slug, (string) ($data['description'] ?? ''));
        $this->audit->record($actor, 'category.updated', 'category', $id, [
            'name' => $data['name'],
            'previous_name' => $current->name,
        ]);
    }

    public function setActive(int $id, bool $active, Actor $actor): void
    {
        $this->get($id);
        $this->categories->setActive($id, $active);
        $this->audit->record($actor, $active ? 'category.activated' : 'category.deactivated', 'category', $id);
    }

    public function move(int $id, string $direction, Actor $actor): void
    {
        $ids = array_map(static fn (Category $c): int => $c->id, $this->categories->all());
        $index = array_search($id, $ids, true);
        if ($index === false) {
            throw new NotFoundException('Category not found.');
        }

        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if ($target < 0 || $target >= count($ids)) {
            return;
        }
        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
        $this->categories->reorder($ids);
        $this->audit->record($actor, 'category.reordered', 'category', $id, ['direction' => $direction]);
    }

    public function delete(int $id, Actor $actor): void
    {
        $category = $this->get($id);
        $usage = $this->categories->jobUsage($id);
        $total = $usage['live'] + $usage['deleted'];
        if ($total > 0) {
            $detail = $usage['deleted'] > 0 ? " ({$usage['deleted']} of them deleted)" : '';
            throw new DomainException(
                "\"{$category->name}\" is used by {$total} job(s){$detail}, so it can't be deleted. "
                . 'Move those jobs to another category, or deactivate this category to hide it.',
            );
        }

        $this->categories->delete($id);
        $this->audit->record($actor, 'category.deleted', 'category', $id, ['name' => $category->name]);
    }

    public function ensure(string $name, Actor $actor): Category
    {
        return $this->categories->findByName($name)
            ?? $this->create(['name' => $name, 'slug' => null, 'description' => '', 'is_active' => true], $actor);
    }

    private function resolveSlug(?string $requested, string $name, ?int $exceptId): string
    {
        if ($requested !== null && $requested !== '') {
            if ($this->categories->slugExists($requested, $exceptId)) {
                throw new DomainException('Another category already uses this slug.', 'slug');
            }

            return $requested;
        }

        return Slug::unique(Slug::from($name), fn (string $s): bool => $this->categories->slugExists($s, $exceptId));
    }
}
