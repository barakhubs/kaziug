<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\AppKey;
use App\Core\Csrf;
use App\Core\Session\Session;
use PHPUnit\Framework\TestCase;

final class SessionAndCsrfTest extends TestCase
{
    public function testFlashSurvivesExactlyOneRequest(): void
    {
        $session = new Session(Session::generateId());
        $session->flash('notice', 'Saved');

        $session->ageFlash(); // next request
        $this->assertSame('Saved', $session->getFlash('notice'));

        $session->ageFlash(); // the one after
        $this->assertNull($session->getFlash('notice'));
    }

    public function testRegenerateKeepsDataAndRemembersOldId(): void
    {
        $session = new Session('a' . str_repeat('0', 63), ['k' => 'v'], false);
        $session->regenerate();

        $this->assertNotSame('a' . str_repeat('0', 63), $session->id());
        $this->assertSame('a' . str_repeat('0', 63), $session->previousId());
        $this->assertSame('v', $session->get('k'));
        $this->assertTrue($session->isNew());
    }

    public function testInvalidateClearsData(): void
    {
        $session = new Session(Session::generateId(), ['admin_id' => 1], false);
        $session->invalidate();

        $this->assertNull($session->get('admin_id'));
    }

    public function testCsrfTokenIsBoundToSecretAndKey(): void
    {
        $csrf = new Csrf(new AppKey('base64:' . base64_encode(random_bytes(32))));
        $otherKey = new Csrf(new AppKey('base64:' . base64_encode(random_bytes(32))));
        $secret = $csrf->newSecret();
        $token = $csrf->tokenFor($secret);

        $this->assertTrue($csrf->isValid($secret, $token));
        $this->assertFalse($csrf->isValid($csrf->newSecret(), $token), 'token from another browser');
        $this->assertFalse($otherKey->isValid($secret, $token), 'token signed with another APP_KEY');
        $this->assertFalse($csrf->isValid($secret, null));
        $this->assertFalse($csrf->isValid(null, $token));
    }

    public function testAppKeyRejectsMalformedValues(): void
    {
        $this->assertFalse(AppKey::isValid(''));
        $this->assertFalse(AppKey::isValid('base64:' . base64_encode('short')));
        $this->assertTrue(AppKey::isValid('base64:' . base64_encode(random_bytes(32))));
    }
}
