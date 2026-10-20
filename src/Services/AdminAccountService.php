<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Actor;
use App\Domain\AdminUser;
use App\Domain\DomainException;
use App\Domain\PasswordPolicy;
use App\Repositories\Contracts\AdminUserRepositoryInterface;
use App\Services\Contracts\AdminAccountServiceInterface;
use App\Services\Contracts\AuditServiceInterface;
use App\Services\Contracts\PasswordHasherInterface;

final class AdminAccountService implements AdminAccountServiceInterface
{
    public function __construct(
        private readonly AdminUserRepositoryInterface $admins,
        private readonly PasswordHasherInterface $hasher,
        private readonly AuditServiceInterface $audit,
    ) {
    }

    public function adminExists(): bool
    {
        return $this->admins->exists();
    }

    public function createAdmin(string $email, string $password, Actor $actor): AdminUser
    {
        $email = strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) {
            throw new DomainException('Enter a valid email address.', 'email');
        }
        if ($this->admins->exists()) {
            throw new DomainException('An admin account already exists. Only one admin is allowed.');
        }
        PasswordPolicy::assertAcceptable($password, $email);

        // The database singleton constraint is the final guard if two creations race.
        $admin = $this->admins->create($email, $this->hasher->hash($password));
        $this->audit->record($actor, 'admin.created', 'admin_user', $admin->id, ['email' => $email]);

        return $admin;
    }

    public function changePassword(AdminUser $admin, string $currentPassword, string $newPassword, Actor $actor): AdminUser
    {
        if (!$this->hasher->verify($currentPassword, $admin->passwordHash)) {
            throw new DomainException('Your current password is incorrect.', 'current_password');
        }
        if (hash_equals($currentPassword, $newPassword)) {
            throw new DomainException('The new password must be different from the current one.', 'password');
        }
        PasswordPolicy::assertAcceptable($newPassword, $admin->email);

        $this->admins->updatePasswordHash($admin->id, $this->hasher->hash($newPassword));
        $version = $this->admins->incrementSessionVersion($admin->id);
        $this->audit->record($actor, 'account.password_changed', 'admin_user', $admin->id);

        return $this->reload($admin, $version);
    }

    public function signOutEverywhere(AdminUser $admin, Actor $actor): AdminUser
    {
        $version = $this->admins->incrementSessionVersion($admin->id);
        $this->audit->record($actor, 'account.sessions_revoked', 'admin_user', $admin->id);

        return $this->reload($admin, $version);
    }

    private function reload(AdminUser $admin, int $version): AdminUser
    {
        return $this->admins->findById($admin->id) ?? new AdminUser($admin->id, $admin->email, $admin->passwordHash, $version);
    }
}
