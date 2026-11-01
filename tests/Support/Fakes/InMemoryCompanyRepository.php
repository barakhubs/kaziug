<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\Company;
use App\Repositories\Contracts\CompanyRepositoryInterface;

final class InMemoryCompanyRepository implements CompanyRepositoryInterface
{
    /** @var array<int, array{name: string, website: ?string, mime: ?string, logo: ?string, version: int}> */
    public array $rows = [];

    /** @var array<int, int> company id => job count, set by tests */
    public array $jobCounts = [];

    private int $nextId = 1;

    public function search(string $q, int $limit): array
    {
        $matches = array_filter(array_keys($this->rows), fn (int $id): bool => $q === '' || stripos($this->rows[$id]['name'], $q) !== false);

        return array_slice(array_map($this->company(...), array_values($matches)), 0, $limit);
    }

    public function findById(int $id): ?Company
    {
        return isset($this->rows[$id]) ? $this->company($id) : null;
    }

    public function findByName(string $name): ?Company
    {
        foreach ($this->rows as $id => $row) {
            if (mb_strtolower($row['name']) === mb_strtolower($name)) {
                return $this->company($id);
            }
        }

        return null;
    }

    public function create(string $name, ?string $website): int
    {
        $id = $this->nextId++;
        $this->rows[$id] = ['name' => $name, 'website' => $website, 'mime' => null, 'logo' => null, 'version' => 0];

        return $id;
    }

    public function update(int $id, string $name, ?string $website): void
    {
        $this->rows[$id] = [...$this->rows[$id], 'name' => $name, 'website' => $website];
    }

    public function putLogo(int $id, string $mime, string $bytes): void
    {
        $this->rows[$id] = [...$this->rows[$id], 'mime' => $mime, 'logo' => $bytes, 'version' => $this->rows[$id]['version'] + 1];
    }

    public function logo(int $id): ?array
    {
        $row = $this->rows[$id] ?? null;

        return $row === null || $row['mime'] === null ? null : ['mime_type' => $row['mime'], 'content' => (string) $row['logo']];
    }

    public function delete(int $id): void
    {
        unset($this->rows[$id]);
    }

    private function company(int $id): Company
    {
        $row = $this->rows[$id];

        return new Company($id, $row['name'], $row['website'], $row['mime'] !== null, $row['version'], $this->jobCounts[$id] ?? 0);
    }
}
