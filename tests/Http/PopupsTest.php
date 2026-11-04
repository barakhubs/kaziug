<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Console\Commands\SeedCommand;
use App\Console\Io;
use PDOException;
use Tests\Support\AdminHttpTestCase;

final class PopupsTest extends AdminHttpTestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->container->get(SeedCommand::class)->run(['--only=jobs'], new Io([], echo: false));
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    /** @return array<string, string> */
    private function popupForm(array $overrides = []): array
    {
        return [...[
            'name' => 'CV offer',
            'heading' => 'Get your CV fixed',
            'body_html' => '<p>Stand out to employers in <strong>Kampala</strong>.</p>',
            'button_label' => 'Get CV help',
            'button_url' => '/cv-help',
            'trigger_type' => 'delay',
            'delay_seconds' => '5',
            'activate' => '1',
        ], ...$overrides];
    }

    private function popupId(string $name): int
    {
        return (int) $this->db->fetchValue('SELECT id FROM popups WHERE name = ?', [$name]);
    }

    private function jobPath(): string
    {
        return '/jobs/' . $this->db->fetchValue("SELECT slug FROM jobs WHERE status = 'published' LIMIT 1");
    }

    /** @return array<string, mixed> a $_FILES-style entry holding a small PNG */
    private function image(): array
    {
        $gd = imagecreatetruecolor(80, 40);
        ob_start();
        imagepng($gd);
        $bytes = (string) ob_get_clean();
        $path = (string) tempnam(sys_get_temp_dir(), 'kz');
        file_put_contents($path, $bytes);
        $this->tempFiles[] = $path;

        return ['name' => 'p.png', 'type' => 'image/png', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bytes)];
    }

    public function testActivePopupShowsOnTheHomeAndJobPagesOnly(): void
    {
        $this->assertStatus(200, $this->get('/admin/popups/new'));
        $this->assertRedirect('/admin/popups', $this->post('/admin/popups', $this->popupForm()));
        $id = $this->popupId('CV offer');
        $this->assertSame(1, $this->auditCount('popup.created'));

        $this->cookies = [];
        foreach (['/', $this->jobPath()] as $path) {
            $html = $this->get($path)->body();
            $this->assertStringContainsString('<dialog class="promo" id="promo"', $html, $path);
            $this->assertStringContainsString("data-promo-key=\"{$id}.1\" data-trigger=\"delay\" data-delay=\"5\"", $html);
            $this->assertStringContainsString('<h2 id="promoTitle">Get your CV fixed</h2>', $html);
            $this->assertStringContainsString('<p>Stand out to employers in <strong>Kampala</strong>.</p>', $html);
            $this->assertStringContainsString('<a class="btn-primary promo-cta" href="/cv-help" data-promo-close>Get CV help</a>', $html);
        }
        $category = (string) $this->db->fetchValue('SELECT c.slug FROM job_categories c JOIN jobs j ON j.category_id = c.id LIMIT 1');
        foreach (['/about', '/blog', '/cv-help', '/privacy', "/categories/{$category}", '/jobs-in/kampala', '/saved'] as $path) {
            $this->assertStringNotContainsString('id="promo"', $this->get($path)->body(), $path);
        }
    }

    public function testOnlyOnePopupIsActiveAtATime(): void
    {
        $this->post('/admin/popups', $this->popupForm(['name' => 'First']));
        $this->post('/admin/popups', $this->popupForm(['name' => 'Second', 'heading' => 'Second heading']));
        $first = $this->popupId('First');
        $second = $this->popupId('Second');
        $this->assertSame([$second], array_map('intval', array_column($this->db->fetchAll('SELECT id FROM popups WHERE is_active = 1'), 'id')));

        $this->post("/admin/popups/{$first}/activate");
        $this->assertSame([$first], array_map('intval', array_column($this->db->fetchAll('SELECT id FROM popups WHERE is_active = 1'), 'id')));
        $this->assertSame(1, $this->auditCount('popup.activated'));
        $this->assertStringContainsString('Get your CV fixed', $this->get('/')->body());

        // The database refuses a second active row even if the application were bypassed.
        try {
            $this->db->execute('UPDATE popups SET is_active = 1 WHERE id = ?', [$second]);
            $this->fail('Two active popups were allowed');
        } catch (PDOException $e) {
            $this->assertStringContainsString('popups_one_active_key', $e->getMessage());
        }

        $this->post("/admin/popups/{$first}/deactivate");
        $this->assertSame(0, (int) $this->db->fetchValue('SELECT count(*) FROM popups WHERE is_active = 1'));
        $this->assertStringNotContainsString('id="promo"', $this->get('/')->body());
    }

    public function testDateWindowAndShowAgain(): void
    {
        $tomorrow = (new \DateTimeImmutable('+1 day'))->format('Y-m-d\TH:i');
        $this->post('/admin/popups', $this->popupForm(['starts_at' => $tomorrow]));
        $id = $this->popupId('CV offer');
        $this->assertStringContainsString('<span class="pill scheduled">Scheduled</span>', $this->get('/admin/popups')->body());
        $this->assertStringNotContainsString('id="promo"', $this->get('/')->body());

        $this->db->execute('UPDATE popups SET starts_at = NULL, ends_at = NOW(6) - INTERVAL 1 MINUTE WHERE id = ?', [$id]);
        $this->assertStringContainsString('<span class="pill ended">Ended</span>', $this->get('/admin/popups')->body());
        $this->assertStringNotContainsString('id="promo"', $this->get('/')->body());

        $this->post("/admin/popups/{$id}", $this->popupForm());
        $this->assertStringContainsString("data-promo-key=\"{$id}.1\"", $this->get('/')->body(), 'small edits keep the version');
        $this->post("/admin/popups/{$id}", $this->popupForm(['show_again' => '1']));
        $this->assertStringContainsString("data-promo-key=\"{$id}.2\"", $this->get('/')->body(), 'show again changes what browsers remember');
    }

    public function testValidationAndSanitising(): void
    {
        $bad = $this->post('/admin/popups', $this->popupForm(['button_url' => 'javascript:alert(1)']));
        $this->assertStatus(422, $bad);
        $this->assertStringContainsString('Use a page on this site such as /cv-help', $bad->body());
        $this->assertStatus(422, $this->post('/admin/popups', $this->popupForm(['button_url' => '//evil.example/x'])));
        $this->assertStatus(422, $this->post('/admin/popups', $this->popupForm(['button_label' => ''])));
        $this->assertStatus(422, $this->post('/admin/popups', $this->popupForm(['delay_seconds' => '500'])));
        $this->assertStatus(422, $this->post('/admin/popups', $this->popupForm(['trigger_type' => 'instant'])));
        $this->assertStatus(422, $this->post('/admin/popups', $this->popupForm(['starts_at' => '2026-12-10T10:00', 'ends_at' => '2026-12-01T10:00'])));
        $this->assertSame(0, (int) $this->db->fetchValue('SELECT count(*) FROM popups'));

        $this->post('/admin/popups', $this->popupForm([
            'button_label' => '', 'button_url' => '', 'trigger_type' => 'exit',
            'body_html' => '<p onmouseover="x()">Hi<script>alert(1)</script></p><img src=x onerror=alert(1)>',
        ]));
        $html = $this->get('/')->body();
        $this->assertStringContainsString('<div class="promo-text"><p>Hi</p></div>', $html);
        $this->assertStringContainsString('data-trigger="exit"', $html);
        $this->assertStringNotContainsString('promo-cta', $html);
    }

    public function testImageIsServedOnlyForTheShowingPopup(): void
    {
        $this->post('/admin/popups', $this->popupForm(['image_alt' => 'A CV']), files: ['image' => $this->image()]);
        $id = $this->popupId('CV offer');
        $this->assertStringContainsString("<img class=\"promo-image\" src=\"/media/popups/{$id}/image?v=1\" alt=\"A CV\"", $this->get('/')->body());

        $this->cookies = [];
        $this->assertStatus(200, $this->get("/media/popups/{$id}/image"));
        $this->db->execute('UPDATE popups SET is_active = 0');
        $this->assertStatus(404, $this->get("/media/popups/{$id}/image"));
    }

    public function testDeleteAndSidebarEntry(): void
    {
        $this->post('/admin/popups', $this->popupForm());
        $id = $this->popupId('CV offer');
        $this->assertStringContainsString('<span>Popups</span>', $this->get('/admin')->body());

        $this->post("/admin/popups/{$id}/delete");
        $this->assertSame(0, (int) $this->db->fetchValue('SELECT count(*) FROM popups'));
        $this->assertSame(1, $this->auditCount('popup.deleted'));
        $this->assertStringNotContainsString('id="promo"', $this->get('/')->body());
    }
}
