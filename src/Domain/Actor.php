<?php

declare(strict_types=1);

namespace App\Domain;

/**
 * Who performed an action, and from where. Passed into services so they can audit
 * without knowing about HTTP or the console.
 */
final class Actor
{
    public function __construct(
        public readonly ?int $adminId,
        public readonly string $label,
        public readonly ?string $ip,
    ) {
    }

    public static function admin(AdminUser $admin, string $ip): self
    {
        return new self($admin->id, $admin->email, $ip);
    }

    public static function console(): self
    {
        return new self(null, 'console', null);
    }

    public static function system(): self
    {
        return new self(null, 'system', null);
    }

    public static function visitor(string $ip): self
    {
        return new self(null, 'visitor', $ip);
    }
}
