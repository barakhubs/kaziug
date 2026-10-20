<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Log\LoggerInterface;
use App\Domain\AdminUser;
use App\Domain\LoginResult;
use App\Domain\ThrottlePolicy;
use App\Repositories\Contracts\AdminUserRepositoryInterface;
use App\Services\Contracts\AuthServiceInterface;
use App\Services\Contracts\PasswordHasherInterface;
use App\Services\Contracts\ThrottleInterface;

final class AuthService implements AuthServiceInterface
{
    // Verified when the email is unknown, so response time does not reveal whether it exists.
    private const DUMMY_HASH = '$argon2id$v=19$m=65536,t=4,p=1$S2pqdmI5aWVKTFFRVjhXdQ$cQZAOuabnHhqmZOigIDOK9fAJLOp63P8KuvwiZpQAnE';

    public function __construct(
        private readonly AdminUserRepositoryInterface $admins,
        private readonly PasswordHasherInterface $hasher,
        private readonly ThrottleInterface $throttle,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function attempt(string $email, string $password, string $ip): LoginResult
    {
        $email = strtolower(trim($email));
        $ipKey = 'login:ip:' . $ip;
        $emailKey = 'login:email:' . hash('sha256', $email);

        $locked = max($this->throttle->lockedFor($ipKey), $this->throttle->lockedFor($emailKey));
        if ($locked > 0) {
            return LoginResult::locked($locked);
        }

        $admin = $this->admins->findByEmail($email);
        $valid = $this->hasher->verify($password, $admin?->passwordHash ?? self::DUMMY_HASH) && $admin !== null;

        if (!$valid) {
            $lock = max(
                $this->throttle->hit($ipKey, ThrottlePolicy::loginPerIp()),
                $this->throttle->hit($emailKey, ThrottlePolicy::loginPerEmail()),
            );
            $this->logger->warning('Admin login failed', ['ip' => $ip, 'locked_seconds' => $lock]);

            return $lock > 0 ? LoginResult::locked($lock) : LoginResult::invalid();
        }

        $this->throttle->clear($emailKey);
        $this->throttle->clear($ipKey);
        if ($this->hasher->needsRehash($admin->passwordHash)) {
            $this->admins->updatePasswordHash($admin->id, $this->hasher->hash($password));
        }

        return LoginResult::success($admin);
    }

    public function resolveSession(int $adminId, int $sessionVersion): ?AdminUser
    {
        $admin = $this->admins->findById($adminId);

        return $admin !== null && $admin->sessionVersion === $sessionVersion ? $admin : null;
    }
}
