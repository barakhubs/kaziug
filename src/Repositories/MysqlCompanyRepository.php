<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Domain\Company;
use App\Repositories\Contracts\CompanyRepositoryInterface;
use PDO;

final class MysqlCompanyRepository implements CompanyRepositoryInterface
{
    // The logo bytes are never selected here; they are only read by logo().
    private const SELECT = 'SELECT co.id, co.name, co.website, co.logo_mime IS NOT NULL AS has_logo, co.logo_version,
        (SELECT count(*) FROM jobs j WHERE j.company_id = co.id) AS job_count FROM companies co';

    public function __construct(private readonly Database $db)
    {
    }

    public function search(string $q, int $limit): array
    {
        $q = trim($q);
        if ($q === '') {
            return array_map(self::map(...), $this->db->fetchAll(self::SELECT . ' ORDER BY co.name LIMIT ?', [$limit]));
        }
        $escaped = addcslashes($q, '%_\\');

        return array_map(self::map(...), $this->db->fetchAll(
            self::SELECT . ' WHERE co.name LIKE ? ORDER BY (co.name LIKE ?) DESC, co.name LIMIT ?',
            ['%' . $escaped . '%', $escaped . '%', $limit],
        ));
    }

    public function findById(int $id): ?Company
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE co.id = ?', [$id]);

        return $row === null ? null : self::map($row);
    }

    public function findByName(string $name): ?Company
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE co.name = ?', [$name]);

        return $row === null ? null : self::map($row);
    }

    public function create(string $name, ?string $website): int
    {
        return $this->db->insert('INSERT INTO companies (name, website) VALUES (?, ?)', [$name, $website]);
    }

    public function update(int $id, string $name, ?string $website): void
    {
        $this->db->execute('UPDATE companies SET name = ?, website = ? WHERE id = ?', [$name, $website, $id]);
    }

    public function putLogo(int $id, string $mime, string $bytes): void
    {
        $statement = $this->db->pdo()->prepare(
            'UPDATE companies SET logo_mime = :mime, logo = :logo, logo_version = logo_version + 1 WHERE id = :id',
        );
        $statement->bindValue('mime', $mime);
        $statement->bindValue('logo', $bytes, PDO::PARAM_LOB);
        $statement->bindValue('id', $id, PDO::PARAM_INT);
        $statement->execute();
    }

    public function logo(int $id): ?array
    {
        $row = $this->db->fetchOne('SELECT logo_mime, logo FROM companies WHERE id = ? AND logo_mime IS NOT NULL', [$id]);

        return $row === null ? null : ['mime_type' => (string) $row['logo_mime'], 'content' => (string) $row['logo']];
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM companies WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $row */
    private static function map(array $row): Company
    {
        return new Company(
            (int) $row['id'],
            (string) $row['name'],
            $row['website'] === null ? null : (string) $row['website'],
            (bool) $row['has_logo'],
            (int) $row['logo_version'],
            (int) $row['job_count'],
        );
    }
}
