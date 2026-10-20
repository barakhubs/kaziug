<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Actor;
use App\Services\Contracts\AdminAccountServiceInterface;

/** HTTP tests that run as the signed-in admin. */
abstract class AdminHttpTestCase extends HttpTestCase
{
    protected const ADMIN_EMAIL = 'admin@kazi.ug';
    protected const ADMIN_PASSWORD = 'a long test passphrase';

    protected function setUp(): void
    {
        parent::setUp();
        $this->container->get(AdminAccountServiceInterface::class)
            ->createAdmin(self::ADMIN_EMAIL, self::ADMIN_PASSWORD, Actor::console());
        $response = $this->post('/admin/login', ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD]);
        $this->assertSame(303, $response->status(), 'admin login failed');
    }

    /** @param array<string, mixed> $params */
    protected function auditCount(string $action, array $params = []): int
    {
        return (int) $this->db->fetchValue('SELECT count(*) FROM audit_logs WHERE action = ?', [$action, ...$params]);
    }
}
