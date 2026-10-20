<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\AdminUser;
use App\Domain\LoginResult;

interface AuthServiceInterface
{
    /** Verifies credentials with per-IP and per-email progressive lockout. */
    public function attempt(string $email, string $password, string $ip): LoginResult;

    /** The admin for a session, or null if the admin is gone or the session was revoked. */
    public function resolveSession(int $adminId, int $sessionVersion): ?AdminUser;
}
