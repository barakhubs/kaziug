<?php

declare(strict_types=1);

namespace App\Domain;

final class Category
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $slug,
        public readonly string $description,
        public readonly int $sortOrder,
        public readonly bool $isActive,
        public readonly int $jobCount = 0,
    ) {
    }
}
