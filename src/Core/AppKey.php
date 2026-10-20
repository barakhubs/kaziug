<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * The application master key (APP_KEY) and purpose-specific subkeys derived from it with
 * libsodium's KDF, so a key used for one purpose (e.g. CSRF) never encrypts anything else.
 */
final class AppKey
{
    public const SUBKEY_SMTP = 1;
    public const SUBKEY_CSRF = 2;
    public const SUBKEY_FORMS = 3;
    private const CONTEXT = 'KaziUG01';

    private readonly string $key;

    public function __construct(string $configured)
    {
        $raw = str_starts_with($configured, 'base64:')
            ? base64_decode(substr($configured, 7), true)
            : false;

        if ($raw === false || strlen($raw) !== SODIUM_CRYPTO_KDF_KEYBYTES) {
            throw new RuntimeException('APP_KEY must be "base64:" followed by 32 random bytes encoded in base64. See .env.example.');
        }
        $this->key = $raw;
    }

    public static function isValid(string $configured): bool
    {
        try {
            new self($configured);

            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function derive(int $subkeyId, int $length = 32): string
    {
        return sodium_crypto_kdf_derive_from_key($length, $subkeyId, self::CONTEXT, $this->key);
    }
}
