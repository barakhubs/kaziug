<?php

declare(strict_types=1);

namespace App\Domain;

/** Rules for the admin password. Length matters more than character classes. */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;
    public const MAX_LENGTH = 200;

    /** @throws DomainException */
    public static function assertAcceptable(string $password, string $email): void
    {
        $length = mb_strlen($password);
        if ($length < self::MIN_LENGTH) {
            throw new DomainException('Use at least ' . self::MIN_LENGTH . ' characters.', 'password');
        }
        if ($length > self::MAX_LENGTH) {
            throw new DomainException('Use at most ' . self::MAX_LENGTH . ' characters.', 'password');
        }
        if (strcasecmp($password, $email) === 0 || count(array_unique(mb_str_split($password))) < 4) {
            throw new DomainException('Choose a less predictable password.', 'password');
        }
    }
}
