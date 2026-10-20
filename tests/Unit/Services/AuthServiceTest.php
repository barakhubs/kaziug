<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Domain\ThrottlePolicy;
use App\Services\AuthService;
use App\Services\PasswordHasher;
use App\Services\Throttle;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\InMemoryAdminUserRepository;
use Tests\Support\Fakes\InMemoryThrottleRepository;
use Tests\Support\Fakes\NullLogger;

final class AuthServiceTest extends TestCase
{
    private InMemoryAdminUserRepository $admins;
    private InMemoryThrottleRepository $throttleRepo;
    private AuthService $auth;

    protected function setUp(): void
    {
        $hasher = new PasswordHasher();
        $this->admins = new InMemoryAdminUserRepository();
        $this->admins->create('admin@kazi.ug', $hasher->hash('correct horse battery'));
        $this->throttleRepo = new InMemoryThrottleRepository();
        $this->auth = new AuthService($this->admins, $hasher, new Throttle($this->throttleRepo), new NullLogger());
    }

    public function testCorrectCredentialsSucceedCaseInsensitively(): void
    {
        $result = $this->auth->attempt('  Admin@Kazi.UG ', 'correct horse battery', '10.0.0.1');

        $this->assertTrue($result->succeeded());
        $this->assertSame('admin@kazi.ug', $result->admin?->email);
    }

    public function testWrongPasswordAndUnknownEmailLookTheSame(): void
    {
        $wrongPassword = $this->auth->attempt('admin@kazi.ug', 'nope', '10.0.0.1');
        $unknownEmail = $this->auth->attempt('who@kazi.ug', 'nope', '10.0.0.1');

        $this->assertFalse($wrongPassword->succeeded());
        $this->assertFalse($wrongPassword->isLocked());
        $this->assertEquals($wrongPassword, $unknownEmail);
    }

    public function testEmailIsLockedProgressivelyAfterFreeAttempts(): void
    {
        $free = ThrottlePolicy::loginPerEmail()->freeAttempts;
        for ($i = 0; $i < $free; $i++) {
            $this->assertFalse($this->auth->attempt('admin@kazi.ug', 'bad', "10.0.0.{$i}")->isLocked());
        }

        $first = $this->auth->attempt('admin@kazi.ug', 'bad', '10.0.1.1');
        $this->assertTrue($first->isLocked());
        $this->assertSame(60, $first->lockedSeconds);

        // While locked, even the right password is refused.
        $this->assertTrue($this->auth->attempt('admin@kazi.ug', 'correct horse battery', '10.0.1.2')->isLocked());

        $this->throttleRepo->expireLocks();
        $second = $this->auth->attempt('admin@kazi.ug', 'bad', '10.0.1.3');
        $this->assertSame(120, $second->lockedSeconds, 'lock doubles on each further failure');
    }

    public function testIpIsLockedAcrossDifferentEmails(): void
    {
        $free = ThrottlePolicy::loginPerIp()->freeAttempts;
        for ($i = 0; $i < $free; $i++) {
            $this->auth->attempt("user{$i}@example.com", 'bad', '192.0.2.9');
        }

        $this->assertTrue($this->auth->attempt('someone-new@example.com', 'bad', '192.0.2.9')->isLocked());
        $this->assertFalse($this->auth->attempt('someone-new@example.com', 'bad', '192.0.2.10')->isLocked());
    }

    public function testSuccessClearsCounters(): void
    {
        $this->auth->attempt('admin@kazi.ug', 'bad', '10.0.0.1');
        $this->auth->attempt('admin@kazi.ug', 'correct horse battery', '10.0.0.1');

        $this->assertSame([], $this->throttleRepo->counts);
    }

    public function testResolveSessionRejectsOldSessionVersion(): void
    {
        $this->assertNotNull($this->auth->resolveSession(1, 1));
        $this->admins->incrementSessionVersion(1);

        $this->assertNull($this->auth->resolveSession(1, 1));
        $this->assertNotNull($this->auth->resolveSession(1, 2));
    }

    public function testThrottlePolicyCapsLockLength(): void
    {
        $policy = new ThrottlePolicy(2, 3600, 60, 300);

        $this->assertSame(0, $policy->lockSecondsFor(2));
        $this->assertSame(60, $policy->lockSecondsFor(3));
        $this->assertSame(240, $policy->lockSecondsFor(5));
        $this->assertSame(300, $policy->lockSecondsFor(50));
    }
}
