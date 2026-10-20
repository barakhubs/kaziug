<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\AdminUser;

interface AdminUserRepositoryInterface
{
    public function exists(): bool;

    public function findById(int $id): ?AdminUser;

    public function findByEmail(string $email): ?AdminUser;

    /** @throws \App\Domain\DomainException when an admin already exists (database singleton constraint) */
    public function create(string $email, string $passwordHash): AdminUser;

    public function updatePasswordHash(int $id, string $passwordHash): void;

    /** @return int the new session version */
    public function incrementSessionVersion(int $id): int;
}
