<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\PasswordHasherInterface;

/** argon2id when PHP was built with it, bcrypt (PASSWORD_DEFAULT) otherwise. */
final class PasswordHasher implements PasswordHasherInterface
{
    private readonly string|int|null $algorithm;

    public function __construct()
    {
        $this->algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    public function hash(string $password): string
    {
        return password_hash($password, $this->algorithm);
    }

    public function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, $this->algorithm);
    }
}
