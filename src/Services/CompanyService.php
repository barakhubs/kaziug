<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Actor;
use App\Domain\Company;
use App\Domain\DomainException;
use App\Domain\NotFoundException;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use App\Repositories\Contracts\JobWriteRepositoryInterface;
use App\Repositories\Contracts\TransactionManagerInterface;
use App\Services\Contracts\AuditServiceInterface;
use App\Services\Contracts\CompanyServiceInterface;

final class CompanyService implements CompanyServiceInterface
{
    public function __construct(
        private readonly CompanyRepositoryInterface $companies,
        private readonly JobWriteRepositoryInterface $jobs,
        private readonly ImageInspector $images,
        private readonly TransactionManagerInterface $tx,
        private readonly AuditServiceInterface $audit,
    ) {
    }

    public function search(string $q, int $limit): array
    {
        return $this->companies->search($q, $limit);
    }

    public function get(int $id): Company
    {
        return $this->companies->findById($id) ?? throw new NotFoundException('Company not found.');
    }

    public function findByName(string $name): ?Company
    {
        return $this->companies->findByName($name);
    }

    public function create(array $data, string $logoBytes, Actor $actor): Company
    {
        $this->assertNameFree($data['name'], null);
        if ($logoBytes === '') {
            throw new DomainException('Upload the company logo.', 'logo');
        }
        $mime = $this->images->assertAcceptableLogo($logoBytes);

        $id = $this->tx->transactional(function () use ($data, $mime, $logoBytes): int {
            $id = $this->companies->create($data['name'], $data['website']);
            $this->companies->putLogo($id, $mime, $logoBytes);

            return $id;
        });
        $this->audit->record($actor, 'company.created', 'company', $id, ['name' => $data['name']]);

        return $this->get($id);
    }

    public function update(int $id, array $data, ?string $logoBytes, Actor $actor): void
    {
        $current = $this->get($id);
        $this->assertNameFree($data['name'], $id);
        $mime = $logoBytes === null ? null : $this->images->assertAcceptableLogo($logoBytes);

        $this->tx->transactional(function () use ($id, $data, $mime, $logoBytes, $current): void {
            $this->companies->update($id, $data['name'], $data['website']);
            if ($mime !== null && $logoBytes !== null) {
                $this->companies->putLogo($id, $mime, $logoBytes);
            }
            if ($data['name'] !== $current->name) {
                $this->jobs->reindexCompany($id);
            }
        });
        $this->audit->record($actor, 'company.updated', 'company', $id, array_filter([
            'name' => $data['name'],
            'renamed_from' => $data['name'] !== $current->name ? $current->name : null,
            'logo_replaced' => $mime !== null ? true : null,
        ], static fn (mixed $v): bool => $v !== null));
    }

    public function delete(int $id, Actor $actor): void
    {
        $company = $this->get($id);
        if ($company->jobCount > 0) {
            throw new DomainException("“{$company->name}” still has {$company->jobCount} job(s), including deleted ones, so it can't be deleted.");
        }
        $this->companies->delete($id);
        $this->audit->record($actor, 'company.deleted', 'company', $id, ['name' => $company->name]);
    }

    public function idForImportedName(string $name): int
    {
        return $this->companies->findByName($name)?->id ?? $this->companies->create($name, null);
    }

    public function logo(int $id): ?array
    {
        return $this->companies->logo($id);
    }

    private function assertNameFree(string $name, ?int $exceptId): void
    {
        $existing = $this->companies->findByName($name);
        if ($existing !== null && $existing->id !== $exceptId) {
            throw new DomainException('A company with this name already exists. Pick it from the list instead.', 'name');
        }
    }
}
