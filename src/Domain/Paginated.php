<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * @template T
 */
final class Paginated
{
    /** @param list<T> $items */
    public function __construct(
        public readonly array $items,
        public readonly Pagination $pagination,
    ) {
    }
}
