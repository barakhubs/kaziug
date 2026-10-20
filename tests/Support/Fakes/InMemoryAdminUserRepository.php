<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Domain\AdminUser;
use App\Domain\DomainException;
use App\Repositories\Contracts\AdminUserRepositoryInterface;

final class InMemoryAdminUserRepository implements AdminUserRepositoryInterface
{
    public ?AdminUser $admin = null;

    public function exists(): bool
    {
        return $this->admin !== null;
    }

    public function findById(int $id): ?AdminUser
    {
        return $this->admin?->id === $id ? $this->admin : null;
    }

    public function findByEmail(string $email): ?AdminUser
    {
        return $this->admin?->email === strtolower($email) ? $this->admin : null;
    }

    public function create(string $email, string $passwordHash): AdminUser
    {
        if ($this->admin !== null) {
            throw new DomainException('An admin account already exists.');
        }

        return $this->admin = new AdminUser(1, strtolower($email), $passwordHash, 1);
    }

    public function updatePasswordHash(int $id, string $passwordHash): void
    {
        $a = $this->admin;
        $this->admin = new AdminUser($a->id, $a->email, $passwordHash, $a->sessionVersion);
    }

    public function incrementSessionVersion(int $id): int
    {
        $a = $this->admin;
        $this->admin = new AdminUser($a->id, $a->email, $a->passwordHash, $a->sessionVersion + 1);

        return $this->admin->sessionVersion;
    }
}
