<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Console\Commands\SeedCommand;
use App\Console\Io;
use Tests\Support\AdminHttpTestCase;

final class AdminDashboardAndAuditTest extends AdminHttpTestCase
{
    public function testEmptyDashboardRenders(): void
    {
        $body = $this->get('/admin')->body();

        $this->assertStringContainsString('No jobs yet.', $body);
        $this->assertStringContainsString('None yet', $body);
        $this->assertStringContainsString('Email isn’t set up yet', $body);
    }

    public function testDashboardCountsAndRecentActivity(): void
    {
        $this->container->get(SeedCommand::class)->run(['--only=jobs'], new Io([], echo: false));
        $this->db->execute("UPDATE jobs SET status = 'draft', published_at = NULL WHERE id IN (SELECT id FROM jobs ORDER BY id LIMIT 2)");
        $this->db->execute("INSERT INTO subscribers (email, status, unsubscribe_token) VALUES ('a@x.ug', 'active', ?), ('b@x.ug', 'pending', ?)", [str_repeat('a', 43), str_repeat('b', 43)]);
        $this->post('/admin/newsletters', ['subject' => 'Weekly <jobs>', 'body_html' => '<p>Hi</p>']);

        $body = $this->get('/admin')->body();

        $this->assertMatchesRegularExpression('#Published jobs</span><strong>12</strong>#', $body);
        $this->assertMatchesRegularExpression('#Drafts</span><strong>2</strong>#', $body);
        $this->assertMatchesRegularExpression('#Active subscribers</span><strong>1</strong>#', $body);
        $this->assertStringContainsString('Weekly &lt;jobs&gt;', $body);
        $this->assertStringContainsString('newsletter.created', $body);
        $this->assertStringContainsString('category.created', $body, 'seeding is audited as the console');
        $this->assertMatchesRegularExpression('#<span>Technology</span>\s*<svg class="bar"#', $body);
    }

    public function testAuditLogFiltersAndEscaping(): void
    {
        $this->post('/admin/categories', ['name' => '<img src=x onerror=alert(1)>', 'is_active' => '1']);
        $this->post('/admin/categories', ['name' => 'Plain', 'is_active' => '1']);
        $this->db->execute("INSERT INTO audit_logs (actor, action, entity, created_at) VALUES ('console', 'old.thing', 'misc', now() - interval '10 days')");

        $all = $this->get('/admin/audit')->body();
        $this->assertStringContainsString('category.created', $all);
        $this->assertStringContainsString('auth.login', $all);
        $this->assertStringNotContainsString('<img src=x', $all);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $all);
        $this->assertStringContainsString(self::ADMIN_EMAIL, $all);
        $this->assertStringContainsString('127.0.0.1', $all);

        $filtered = $this->get('/admin/audit', ['action' => 'category.created'])->body();
        $this->assertStringContainsString('2 entries', $filtered);
        $this->assertStringNotContainsString('<code>auth.login</code>', $filtered);

        $this->assertStringContainsString('1 entry', $this->get('/admin/audit', ['to' => date('Y-m-d', strtotime('-5 days'))])->body());
        $this->assertStringContainsString('2 entries', $this->get('/admin/audit', ['entity' => 'category'])->body());

        // Unknown filter values are ignored rather than passed to SQL.
        $this->assertStatus(200, $this->get('/admin/audit', ['action' => "x' OR 1=1 --", 'from' => '2026-13-45']));
    }

    public function testEveryAdminAreaIsAudited(): void
    {
        $this->post('/admin/categories', ['name' => 'Tech', 'is_active' => '1']);
        $categoryId = (int) $this->db->fetchValue('SELECT id FROM job_categories');
        $this->post('/admin/jobs', ['title' => 'Dev', 'company' => 'Co', 'location' => 'Kampala', 'job_type' => 'full-time',
            'experience_level' => 'mid', 'category_id' => (string) $categoryId, 'summary' => 'S', 'intent' => 'draft']);
        $this->post('/admin/social', ['platform' => 'X', 'label' => 'X', 'url' => 'https://x.com/k', 'icon_key' => 'x']);
        $this->post('/admin/settings', ['site_name' => 'KaziUG', 'jobs_per_page' => '9']);
        $this->post('/admin/settings/smtp', ['host' => 'smtp.test', 'port' => '25', 'encryption' => 'none', 'from_email' => 'a@b.ug']);
        $this->db->execute("INSERT INTO subscribers (email, status, unsubscribe_token) VALUES ('s@x.ug', 'active', ?)", [str_repeat('s', 43)]);
        $this->post('/admin/subscribers/' . $this->db->fetchValue('SELECT id FROM subscribers') . '/unsubscribe');
        $this->post('/admin/newsletters', ['subject' => 'N', 'body_html' => '<p>x</p>']);

        $entities = array_column($this->db->fetchAll('SELECT DISTINCT entity FROM audit_logs'), 'entity');
        foreach (['job', 'category', 'social_profile', 'settings', 'smtp_settings', 'subscriber', 'newsletter', 'admin_user'] as $entity) {
            $this->assertContains($entity, $entities, "{$entity} actions are audited");
        }
        $this->assertSame(0, (int) $this->db->fetchValue("SELECT count(*) FROM audit_logs WHERE actor <> ? AND actor <> 'console'", [self::ADMIN_EMAIL]));
    }
}
