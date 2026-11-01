<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\Actor;
use App\Domain\Company;

interface CompanyServiceInterface
{
    /** @return list<Company> */
    public function search(string $q, int $limit): array;

    public function get(int $id): Company;

    public function findByName(string $name): ?Company;

    /**
     * Creates a company from the admin. The logo is required.
     * @param array{name: string, website: ?string} $data
     */
    public function create(array $data, string $logoBytes, Actor $actor): Company;

    /**
     * @param array{name: string, website: ?string} $data
     * @param string|null $logoBytes a replacement logo, or null to keep the current one
     */
    public function update(int $id, array $data, ?string $logoBytes, Actor $actor): void;

    /** Blocked while any job (deleted ones included) still references the company. */
    public function delete(int $id, Actor $actor): void;

    /**
     * The company with this name, created without a logo when it doesn't exist yet.
     * Only for imports and seeds, which have no logo to give.
     */
    public function idForImportedName(string $name): int;

    /** @return array{mime_type: string, content: string}|null */
    public function logo(int $id): ?array;
}
