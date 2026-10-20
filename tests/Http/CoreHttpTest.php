<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Core\Database\Migrator;
use Tests\Support\HttpTestCase;

final class CoreHttpTest extends HttpTestCase
{
    public function testHealthReportsDatabaseAndSendsSecurityHeaders(): void
    {
        $response = $this->get('/health');

        $this->assertStatus(200, $response);
        $this->assertSame(['status' => 'ok', 'checks' => ['database' => true]], json_decode($response->body(), true));
        $this->assertStringContainsString("default-src 'self'", (string) $response->header('Content-Security-Policy'));
        $this->assertSame('DENY', $response->header('X-Frame-Options'));
        $this->assertSame('nosniff', $response->header('X-Content-Type-Options'));
        $this->assertNotNull($response->header('Referrer-Policy'));
    }

    public function testUnknownPageIs404WithoutDebugDetails(): void
    {
        $response = $this->get('/does-not-exist');

        $this->assertStatus(404, $response);
        $this->assertStringNotContainsString('debug-trace', $response->body());
    }

    public function testPostWithoutCsrfTokenIsRejected(): void
    {
        $this->assertStatus(419, $this->post('/health', [], withToken: false));
        $this->assertStatus(419, $this->post('/health', ['_token' => 'forged']));
    }

    public function testPostWithValidTokenPassesCsrfAndReachesRouting(): void
    {
        $this->assertStatus(405, $this->post('/health'));
    }

    public function testAnonymousRequestsCreateNoSessionRows(): void
    {
        $this->get('/health');
        $this->get('/does-not-exist');

        $this->assertSame(0, (int) $this->db->fetchValue('SELECT count(*) FROM sessions'));
    }

    public function testMigrationsAreIdempotent(): void
    {
        $this->assertSame([], $this->container->get(Migrator::class)->migrate());
    }
}
