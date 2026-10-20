<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Console\Commands\SeedCommand;
use App\Console\Io;
use App\Core\Http\Router;
use Tests\Support\AdminHttpTestCase;

/**
 * Requests every GET route in config/routes.php, with route parameters filled from seeded
 * data: public routes as a visitor, admin routes as the admin. Nothing may return 5xx, and
 * every admin route must refuse visitors.
 */
final class RouteSmokeTest extends AdminHttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->container->get(SeedCommand::class)->run([], new Io([], echo: false));
        $this->db->execute("INSERT INTO subscribers (email, status, unsubscribe_token) VALUES ('s@x.ug', 'active', ?)", [str_repeat('k', 43)]);
        $this->post('/admin/newsletters', ['subject' => 'Smoke', 'body_html' => '<p>x</p>']);
    }

    /** @return list<string> concrete paths for every GET route */
    private function getPaths(): array
    {
        $values = [
            'id' => [
                '/admin/jobs/' => (string) $this->db->fetchValue('SELECT min(id) FROM jobs'),
                '/admin/categories/' => (string) $this->db->fetchValue('SELECT min(id) FROM job_categories'),
                '/admin/settings/social/' => (string) $this->db->fetchValue('SELECT min(id) FROM social_profiles'),
                '/admin/newsletters/' => (string) $this->db->fetchValue('SELECT min(id) FROM newsletters'),
            ],
            'slug' => [
                '/jobs/' => (string) $this->db->fetchValue('SELECT min(slug) FROM jobs'),
                '/categories/' => 'technology',
            ],
            'token' => str_repeat('k', 43),
        ];

        $paths = [];
        foreach ($this->container->get(Router::class)->routes() as $route) {
            if ($route->method !== 'GET') {
                continue;
            }
            $path = preg_replace_callback('/\{(\w+)(?::[^}]+)?\}/', function (array $m) use ($route, $values): string {
                $candidates = $values[$m[1]];
                if (is_string($candidates)) {
                    return $candidates;
                }
                foreach ($candidates as $prefix => $value) {
                    if (str_starts_with($route->pattern, $prefix)) {
                        return $value;
                    }
                }
                $this->fail("No test value for {$m[1]} in {$route->pattern}");
            }, $route->pattern);
            $paths[] = (string) $path;
        }

        return $paths;
    }

    public function testEveryGetRouteRendersForTheRightAudience(): void
    {
        $paths = $this->getPaths();
        $this->assertGreaterThan(30, count($paths));

        foreach ($paths as $path) {
            $status = $this->get($path)->status();
            $this->assertLessThan(500, $status, "{$path} as admin");
            if ($path !== '/subscribe/confirm/' . str_repeat('k', 43)) {
                $this->assertContains($status, [200, 303, 404], "{$path} as admin returned {$status}");
            }
        }

        $this->cookies = [];
        foreach ($paths as $path) {
            $response = $this->get($path);
            $this->assertLessThan(500, $response->status(), "{$path} as visitor");
            if (str_starts_with($path, '/admin') && $path !== '/admin/login') {
                $this->assertRedirect('/admin/login', $response);
            }
        }
    }

    public function testEveryAdminPostRouteRequiresLogin(): void
    {
        $this->cookies = [];
        foreach ($this->container->get(Router::class)->routes() as $route) {
            if ($route->method !== 'POST' || !str_starts_with($route->pattern, '/admin') || $route->pattern === '/admin/login') {
                continue;
            }
            $path = (string) preg_replace('/\{(\w+)(?::[^}]+)?\}/', '1', $route->pattern);
            $this->assertRedirect('/admin/login', $this->post($path, ['anything' => 'x']));
        }
        $this->assertSame(0, (int) $this->db->fetchValue("SELECT count(*) FROM audit_logs WHERE actor = 'visitor'"));
    }
}
