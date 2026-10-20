<?php

declare(strict_types=1);

namespace App\Domain;

final class LoginResult
{
    private function __construct(
        public readonly ?AdminUser $admin,
        public readonly int $lockedSeconds,
    ) {
    }

    public static function success(AdminUser $admin): self
    {
        return new self($admin, 0);
    }

    public static function invalid(): self
    {
        return new self(null, 0);
    }

    public static function locked(int $seconds): self
    {
        return new self(null, $seconds);
    }

    public function succeeded(): bool
    {
        return $this->admin !== null;
    }

    public function isLocked(): bool
    {
        return $this->lockedSeconds > 0;
    }
}
