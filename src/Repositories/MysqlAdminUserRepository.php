<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Domain\AdminUser;
use App\Domain\DomainException;
use App\Repositories\Contracts\AdminUserRepositoryInterface;
use PDOException;

final class MysqlAdminUserRepository implements AdminUserRepositoryInterface
{
    private const COLUMNS = 'id, email, password_hash, session_version';

    public function __construct(private readonly Database $db)
    {
    }

    public function exists(): bool
    {
        return (bool) $this->db->fetchValue('SELECT EXISTS (SELECT 1 FROM admin_users)');
    }

    public function findById(int $id): ?AdminUser
    {
        return $this->map($this->db->fetchOne('SELECT ' . self::COLUMNS . ' FROM admin_users WHERE id = ?', [$id]));
    }

    public function findByEmail(string $email): ?AdminUser
    {
        return $this->map($this->db->fetchOne('SELECT ' . self::COLUMNS . ' FROM admin_users WHERE email = ?', [strtolower($email)]));
    }

    public function create(string $email, string $passwordHash): AdminUser
    {
        try {
            $id = $this->db->insert(
                'INSERT INTO admin_users (email, password_hash) VALUES (?, ?)',
                [strtolower($email), $passwordHash],
            );
        } catch (PDOException $e) {
            // 23000: the unique key on admin_users.singleton already holds a row.
            if ($e->getCode() === '23000') {
                throw new DomainException('An admin account already exists. Only one admin is allowed.');
            }
            throw $e;
        }

        return $this->findById($id) ?? throw new DomainException('Could not create the admin.');
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $this->db->execute('UPDATE admin_users SET password_hash = ? WHERE id = ?', [$passwordHash, $id]);
    }

    public function incrementSessionVersion(int $id): int
    {
        $this->db->execute('UPDATE admin_users SET session_version = session_version + 1 WHERE id = ?', [$id]);

        return (int) $this->db->fetchValue('SELECT session_version FROM admin_users WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed>|null $row */
    private function map(?array $row): ?AdminUser
    {
        if ($row === null) {
            return null;
        }

        return new AdminUser((int) $row['id'], (string) $row['email'], (string) $row['password_hash'], (int) $row['session_version']);
    }
}
