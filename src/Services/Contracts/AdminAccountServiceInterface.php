<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\Actor;
use App\Domain\AdminUser;

interface AdminAccountServiceInterface
{
    public function adminExists(): bool;

    /** @throws \App\Domain\DomainException if an admin exists or the input is invalid */
    public function createAdmin(string $email, string $password, Actor $actor): AdminUser;

    /**
     * Also revokes every other session (bumps the session version).
     * @return AdminUser the admin with the new session version
     * @throws \App\Domain\DomainException
     */
    public function changePassword(AdminUser $admin, string $currentPassword, string $newPassword, Actor $actor): AdminUser;

    /** @return AdminUser the admin with the new session version */
    public function signOutEverywhere(AdminUser $admin, Actor $actor): AdminUser;
}
