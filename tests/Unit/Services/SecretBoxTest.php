<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Core\AppKey;
use App\Services\SecretBox;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SecretBoxTest extends TestCase
{
    private function box(?string $key = null): SecretBox
    {
        return new SecretBox(new AppKey($key ?? 'base64:' . base64_encode(str_repeat('k', 32))));
    }

    public function testRoundTripWithRandomNonce(): void
    {
        $box = $this->box();
        $a = $box->encrypt('smtp-p@ss');
        $b = $box->encrypt('smtp-p@ss');

        $this->assertNotSame($a, $b, 'fresh nonce per encryption');
        $this->assertStringNotContainsString('smtp-p@ss', base64_decode($a));
        $this->assertSame('smtp-p@ss', $box->decrypt($a));
    }

    public function testTamperedCiphertextIsRejected(): void
    {
        $raw = base64_decode($this->box()->encrypt('secret'));
        $raw[strlen($raw) - 1] = chr(ord($raw[strlen($raw) - 1]) ^ 1);

        $this->expectException(RuntimeException::class);
        $this->box()->decrypt(base64_encode($raw));
    }

    public function testDifferentAppKeyCannotDecrypt(): void
    {
        $encrypted = $this->box()->encrypt('secret');

        $this->expectException(RuntimeException::class);
        $this->box('base64:' . base64_encode(str_repeat('z', 32)))->decrypt($encrypted);
    }
}
