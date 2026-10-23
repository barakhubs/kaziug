<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Repositories\Contracts\ThrottleRepositoryInterface;
use App\Services\Throttle;
use Tests\Support\HttpTestCase;

final class LegalPagesTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->execute("UPDATE settings SET value = '\"privacy@kazi.ug\"' WHERE key = 'contact_email'");
        $this->db->execute("UPDATE settings SET value = '\"256772123456\"' WHERE key = 'whatsapp_number'");
        $this->db->execute('UPDATE settings SET value = ? WHERE key = ?', [json_encode("Plot 1, Kampala Road\nKampala"), 'physical_address']);
    }

    public function testEachLegalPageRendersWithFilledPlaceholders(): void
    {
        foreach (['/privacy' => 'Privacy Policy', '/terms' => 'Terms of Use', '/cookies' => 'Cookie Policy'] as $path => $title) {
            $page = $this->get($path);
            $html = $page->body();

            $this->assertStatus(200, $page);
            $this->assertStringContainsString("<h1>{$title}</h1>", $html);
            $this->assertStringContainsString('Last updated <time datetime=', $html);
            $this->assertStringContainsString('<link rel="canonical" href="http://localhost' . $path . '">', $html);
            $this->assertDoesNotMatchRegularExpression('/\{(site|url|contact|address|price)\}/', $html, "{$path} has an unfilled placeholder");
            $this->assertStringContainsString('class="legal-toc', $html);
        }
    }

    public function testPrivacyPolicyNamesTheLawRightsAndContactDetails(): void
    {
        $html = html_entity_decode($this->get('/privacy')->body());

        $this->assertStringContainsString('Data Protection and Privacy Act, 2019', $html);
        $this->assertStringContainsString('Personal Data Protection Office', $html);
        $this->assertStringContainsString('by email at privacy@kazi.ug, on WhatsApp at +256 772 123456 or through the contact form at http://localhost/contact', $html);
        $this->assertStringContainsString('Plot 1, Kampala Road, Kampala', $html);
        $this->assertStringContainsString('deleted automatically within 2 days', $html);
    }

    public function testCookiePolicyListsTheCookiesTheSiteActuallySets(): void
    {
        $html = $this->get('/cookies')->body();

        // Every cookie the app can set must be documented.
        $this->get('/');
        foreach (array_keys($this->cookies) as $cookie) {
            $this->assertStringContainsString("<th scope=\"row\">{$cookie}</th>", $html, "{$cookie} is not in the cookie policy");
        }
        $this->assertStringContainsString('<th scope="row">kaziug_session</th>', $html);
        $this->assertStringContainsString('kazi-saved-jobs', $html);
    }

    public function testLegalLinksAppearInFooterFormsAndSitemap(): void
    {
        $home = $this->get('/')->body();
        foreach (['/privacy', '/terms', '/cookies'] as $path) {
            $this->assertStringContainsString("href=\"{$path}\"", $home);
            $this->assertStringContainsString("<loc>http://localhost{$path}</loc>", $this->get('/sitemap.xml')->body());
        }
        $this->assertStringContainsString('unsubscribe at any time. See our <a href="/privacy">', $home);
        $this->db->execute("INSERT INTO smtp_settings (id, host, port, encryption, from_email) VALUES (1, 'smtp.test', 25, 'none', 'jobs@kazi.test')");
        $this->assertStringContainsString('We use your details only to reply to you.', $this->get('/contact')->body());
    }

    public function testOldRateLimitRecordsWithIpAddressesArePurged(): void
    {
        $this->db->execute(
            "INSERT INTO login_attempts (key, failure_count, updated_at, locked_until) VALUES
             ('subscribe:ip:10.0.0.1', 3, now() - interval '3 days', NULL),
             ('contact:ip:10.0.0.2', 9, now() - interval '3 days', now() + interval '1 hour'),
             ('contact:ip:10.0.0.3', 1, now() - interval '1 hour', NULL)",
        );

        $removed = $this->container->get(ThrottleRepositoryInterface::class)->purgeOlderThan(Throttle::RETENTION_SECONDS);

        $this->assertSame(1, $removed);
        $this->assertSame(
            ['contact:ip:10.0.0.2', 'contact:ip:10.0.0.3'],
            array_column($this->db->fetchAll('SELECT key FROM login_attempts ORDER BY key'), 'key'),
            'still-locked and recent records are kept',
        );
    }
}
