<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\AppKey;
use RuntimeException;

/**
 * Authenticated symmetric encryption (libsodium secretbox, XSalsa20-Poly1305) for secrets at
 * rest, such as the SMTP password. The key is derived from APP_KEY for this purpose only.
 */
final class SecretBox
{
    private readonly string $key;

    public function __construct(AppKey $appKey)
    {
        $this->key = $appKey->derive(AppKey::SUBKEY_SMTP, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }

    public function encrypt(#[\SensitiveParameter] string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return base64_encode($nonce . sodium_crypto_secretbox($plaintext, $nonce, $this->key));
    }

    /** @throws RuntimeException when the value was tampered with or APP_KEY changed */
    public function decrypt(string $encoded): string
    {
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Encrypted value is malformed.');
        }
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);
        if ($plain === false) {
            throw new RuntimeException('Could not decrypt the stored secret. Was APP_KEY changed?');
        }

        return $plain;
    }
}
