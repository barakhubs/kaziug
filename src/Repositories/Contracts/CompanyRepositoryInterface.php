<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\Company;

interface CompanyRepositoryInterface
{
    /** @return list<Company> companies whose name contains $q (all when empty), names starting with $q first */
    public function search(string $q, int $limit): array;

    public function findById(int $id): ?Company;

    /** Case-insensitive exact match. */
    public function findByName(string $name): ?Company;

    public function create(string $name, ?string $website): int;

    public function update(int $id, string $name, ?string $website): void;

    /** Replaces the logo and bumps its version. */
    public function putLogo(int $id, string $mime, string $bytes): void;

    /** @return array{mime_type: string, content: string}|null */
    public function logo(int $id): ?array;

    public function delete(int $id): void;
}
