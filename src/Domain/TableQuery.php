<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Paging and sorting state of a back-office table, read from and written back to the query
 * string (?page=2&per_page=50&sort=title&dir=asc). Only sort keys the caller allows are accepted;
 * repositories map those keys to SQL themselves.
 */
final class TableQuery
{
    public const PER_PAGE_OPTIONS = [10, 20, 50, 100];

    private function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly string $sort,
        public readonly string $direction,
        private readonly string $defaultSort,
        private readonly string $defaultDirection,
        private readonly int $defaultPerPage,
    ) {
    }

    /**
     * @param array<string, mixed> $query raw $_GET
     * @param list<string> $sortable allowed sort keys
     */
    public static function fromQuery(array $query, array $sortable, string $defaultSort, string $defaultDirection = 'desc', int $defaultPerPage = 20): self
    {
        $sort = is_string($query['sort'] ?? null) && in_array($query['sort'], $sortable, true) ? $query['sort'] : $defaultSort;
        $direction = in_array($query['dir'] ?? null, ['asc', 'desc'], true) ? (string) $query['dir'] : $defaultDirection;
        $perPage = (int) ($query['per_page'] ?? $defaultPerPage);
        if (!in_array($perPage, self::PER_PAGE_OPTIONS, true)) {
            $perPage = $defaultPerPage;
        }
        $page = is_numeric($query['page'] ?? null) ? max(1, min(100000, (int) $query['page'])) : 1;

        return new self($page, $perPage, $sort, $direction, $defaultSort, $defaultDirection, $defaultPerPage);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    public function withPage(int $page): self
    {
        return new self($page, $this->perPage, $this->sort, $this->direction, $this->defaultSort, $this->defaultDirection, $this->defaultPerPage);
    }

    public function isSortedBy(string $key): bool
    {
        return $this->sort === $key;
    }

    /** Clicking a header: same column flips the direction, a new column starts descending for dates and ascending otherwise. */
    public function directionFor(string $key, string $firstDirection = 'asc'): string
    {
        if ($this->sort !== $key) {
            return $firstDirection;
        }

        return $this->direction === 'asc' ? 'desc' : 'asc';
    }

    /**
     * Query parameters for links; defaults are left out to keep URLs short.
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public function toQuery(array $overrides = []): array
    {
        $query = [
            'sort' => $this->sort !== $this->defaultSort ? $this->sort : null,
            'dir' => $this->direction !== $this->defaultDirection || $this->sort !== $this->defaultSort ? $this->direction : null,
            'per_page' => $this->perPage !== $this->defaultPerPage ? $this->perPage : null,
            'page' => $this->page > 1 ? $this->page : null,
            ...$overrides,
        ];

        return array_filter($query, static fn (mixed $v): bool => $v !== null && $v !== '');
    }
}
