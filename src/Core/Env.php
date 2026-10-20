<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Typed access to environment variables. Real process env wins over .env (phpdotenv is immutable).
 */
final class Env
{
    public static function string(string $name, string $default = ''): string
    {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);

        if ($value === false || $value === null) {
            return $default;
        }

        return (string) $value;
    }

    public static function int(string $name, int $default): int
    {
        $value = self::string($name);

        return is_numeric($value) ? (int) $value : $default;
    }

    public static function bool(string $name, bool $default): bool
    {
        $value = strtolower(self::string($name));

        return match ($value) {
            '1', 'true', 'yes', 'on' => true,
            '0', 'false', 'no', 'off' => false,
            default => $default,
        };
    }
}
