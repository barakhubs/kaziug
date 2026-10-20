<?php

declare(strict_types=1);

namespace App\Domain;

final class Pagination
{
    public readonly int $page;
    public readonly int $pages;

    public function __construct(int $page, public readonly int $perPage, public readonly int $total)
    {
        $this->pages = max(1, (int) ceil($total / max(1, $perPage)));
        $this->page = min(max(1, $page), $this->pages);
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }

    /** 1-based index of the first row on this page (0 when there are no rows). */
    public function from(): int
    {
        return $this->total === 0 ? 0 : $this->offset() + 1;
    }

    public function to(): int
    {
        return min($this->total, $this->page * $this->perPage);
    }

    public function hasPrevious(): bool
    {
        return $this->page > 1;
    }

    public function hasNext(): bool
    {
        return $this->page < $this->pages;
    }

    /**
     * Page numbers to show, with null for a gap: 1 … 4 5 [6] 7 8 … 20
     * @return list<int|null>
     */
    public function window(int $around = 2): array
    {
        $pages = [];
        $last = 0;
        for ($p = 1; $p <= $this->pages; $p++) {
            if ($p === 1 || $p === $this->pages || abs($p - $this->page) <= $around) {
                if ($last !== 0 && $p - $last > 1) {
                    $pages[] = null;
                }
                $pages[] = $p;
                $last = $p;
            }
        }

        return $pages;
    }
}
