<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\Actor;
use App\Domain\DomainException;
use App\Services\AdminAccountService;
use App\Services\PasswordHasher;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\InMemoryAdminUserRepository;
use Tests\Support\Fakes\SpyAuditService;

final class AdminAccountServiceTest extends TestCase
{
    private InMemoryAdminUserRepository $admins;
    private SpyAuditService $audit;
    private AdminAccountService $service;

    protected function setUp(): void
    {
        $this->admins = new InMemoryAdminUserRepository();
        $this->audit = new SpyAuditService();
        $this->service = new AdminAccountService($this->admins, new PasswordHasher(), $this->audit);
    }

    public function testCreatesAdminWithHashedPasswordAndAudits(): void
    {
        $admin = $this->service->createAdmin('Boss@Kazi.UG', 'a long enough passphrase', Actor::console());

        $this->assertSame('boss@kazi.ug', $admin->email);
        $this->assertStringStartsWith('$argon2id$', $admin->passwordHash);
        $this->assertSame(['admin.created'], $this->audit->actions());
    }

    public function testRefusesSecondAdmin(): void
    {
        $this->service->createAdmin('one@kazi.ug', 'a long enough passphrase', Actor::console());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('already exists');
        $this->service->createAdmin('two@kazi.ug', 'a long enough passphrase', Actor::console());
    }

    public function testRejectsShortOrInvalidInput(): void
    {
        try {
            $this->service->createAdmin('not-an-email', 'a long enough passphrase', Actor::console());
            $this->fail('invalid email accepted');
        } catch (DomainException $e) {
            $this->assertSame('email', $e->field);
        }

        $this->expectException(DomainException::class);
        $this->service->createAdmin('ok@kazi.ug', 'short', Actor::console());
    }

    public function testChangePasswordNeedsCurrentPasswordAndRevokesSessions(): void
    {
        $admin = $this->service->createAdmin('one@kazi.ug', 'a long enough passphrase', Actor::console());

        try {
            $this->service->changePassword($admin, 'wrong current', 'another long passphrase', Actor::console());
            $this->fail('wrong current password accepted');
        } catch (DomainException $e) {
            $this->assertSame('current_password', $e->field);
        }

        $updated = $this->service->changePassword($admin, 'a long enough passphrase', 'another long passphrase', Actor::console());

        $this->assertSame(2, $updated->sessionVersion);
        $this->assertTrue(password_verify('another long passphrase', $updated->passwordHash));
        $this->assertContains('account.password_changed', $this->audit->actions());
    }

    public function testSignOutEverywhereBumpsSessionVersion(): void
    {
        $admin = $this->service->createAdmin('one@kazi.ug', 'a long enough passphrase', Actor::console());

        $this->assertSame(2, $this->service->signOutEverywhere($admin, Actor::console())->sessionVersion);
        $this->assertContains('account.sessions_revoked', $this->audit->actions());
    }
}
