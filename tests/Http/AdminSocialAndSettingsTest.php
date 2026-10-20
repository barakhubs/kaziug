<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\AdminHttpTestCase;

final class AdminSocialAndSettingsTest extends AdminHttpTestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    /** @return array<string, string> */
    private function settingsForm(array $overrides = []): array
    {
        return [...[
            'site_name' => 'KaziUG',
            'tagline' => 'Jobs in Uganda',
            'contact_email' => 'hello@kazi.ug',
            'physical_address' => "Plot 1, Kampala Road\nKampala, Uganda",
            'footer_text' => 'Find work across Uganda.',
            'jobs_per_page' => '8',
            'meta_description' => 'Jobs in Uganda.',
        ], ...$overrides];
    }

    /** @return array<string, mixed> a $_FILES-style entry */
    private function upload(string $bytes, string $name = 'logo.png'): array
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'kz');
        file_put_contents($path, $bytes);
        $this->tempFiles[] = $path;

        return ['name' => $name, 'type' => 'image/png', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bytes)];
    }

    private function png(int $width = 40, int $height = 20): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public function testSocialProfileCrudAndFooter(): void
    {
        $this->assertStatus(200, $this->get('/admin/social'));
        $this->assertStatus(200, $this->get('/admin/social/new'));

        $bad = $this->post('/admin/social', ['platform' => 'X', 'label' => 'X', 'url' => 'javascript:alert(1)', 'icon_key' => 'x']);
        $this->assertStatus(422, $bad);
        $this->assertStatus(422, $this->post('/admin/social', ['platform' => 'X', 'label' => 'X', 'url' => 'https://x.com/k', 'icon_key' => 'myspace']));

        $this->post('/admin/social', ['platform' => 'LinkedIn', 'label' => 'Us on LinkedIn', 'url' => 'https://linkedin.com/company/k', 'icon_key' => 'linkedin', 'is_visible' => '1']);
        $this->post('/admin/social', ['platform' => 'X', 'label' => 'Us on X', 'url' => 'https://x.com/k', 'icon_key' => 'x', 'is_visible' => '1']);
        $this->post('/admin/social', ['platform' => 'TikTok', 'label' => 'Us on TikTok', 'url' => 'https://tiktok.com/@k', 'icon_key' => 'tiktok']);
        [$linkedin, $x, $tiktok] = array_map('intval', array_column($this->db->fetchAll('SELECT id FROM social_profiles ORDER BY id'), 'id'));

        $footer = $this->get('/')->body();
        $this->assertStringContainsString('https://linkedin.com/company/k', $footer);
        $this->assertStringNotContainsString('tiktok.com', $footer, 'hidden profiles stay out of the footer');
        $this->assertLessThan(strpos($footer, 'x.com/k'), strpos($footer, 'linkedin.com'));

        $this->post("/admin/social/{$x}/move", ['direction' => 'up']);
        $this->post("/admin/social/{$tiktok}/visibility", ['visible' => '1']);
        $footer = $this->get('/')->body();
        $this->assertLessThan(strpos($footer, 'linkedin.com'), strpos($footer, 'x.com/k'));
        $this->assertStringContainsString('tiktok.com', $footer);

        $this->post("/admin/social/{$linkedin}", ['platform' => 'LinkedIn', 'label' => 'Changed', 'url' => 'https://linkedin.com/company/new', 'icon_key' => 'linkedin', 'is_visible' => '1']);
        $this->assertStringContainsString('linkedin.com/company/new', $this->get('/')->body());
        $this->post("/admin/social/{$linkedin}/delete");
        $this->assertStringNotContainsString('linkedin.com', $this->get('/')->body());

        foreach (['social_profile.created', 'social_profile.updated', 'social_profile.reordered', 'social_profile.shown', 'social_profile.deleted'] as $action) {
            $this->assertGreaterThan(0, $this->auditCount($action), $action);
        }
    }

    public function testSiteSettingsAreValidatedSavedAndShownPublicly(): void
    {
        $this->assertStatus(200, $this->get('/admin/settings'));
        $this->assertStatus(422, $this->post('/admin/settings', $this->settingsForm(['site_name' => '', 'jobs_per_page' => '500', 'contact_email' => 'x'])));

        $this->assertRedirect('/admin/settings', $this->post('/admin/settings', $this->settingsForm(['site_name' => 'Kazi Jobs', 'jobs_per_page' => '2'])));

        $home = $this->get('/')->body();
        $this->assertStringContainsString('<title>Kazi Jobs: Jobs in Uganda</title>', $home);
        $this->assertStringContainsString('Plot 1, Kampala Road<br />', $home);
        $this->assertStringContainsString('Find work across Uganda.', $home);
        $this->assertSame(2, (int) $this->db->fetchValue("SELECT value::int FROM settings WHERE key = 'jobs_per_page'"));
        $this->assertSame(1, $this->auditCount('settings.updated'));
        $meta = json_decode((string) $this->db->fetchValue("SELECT metadata FROM audit_logs WHERE action = 'settings.updated'"), true);
        $this->assertContains('site_name', $meta['changed']);
    }

    public function testLogoUploadIsValidatedByContent(): void
    {
        $this->assertRedirect('/admin/settings', $this->post('/admin/settings', $this->settingsForm(), files: ['logo' => $this->upload($this->png())]));

        $logo = $this->get('/media/logo');
        $this->assertStatus(200, $logo);
        $this->assertSame('image/png', $logo->header('Content-Type'));
        $this->assertSame($this->png(), $logo->body());
        $this->assertMatchesRegularExpression('#<img src="/media/logo\?v=\d+"#', $this->get('/')->body());

        $rejected = [
            'php disguised as png' => $this->upload("<?php echo 'pwned';", 'logo.png'),
            'svg' => $this->upload('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>', 'logo.svg'),
            'too big' => $this->upload(str_repeat('x', 600 * 1024)),
            'too wide' => $this->upload($this->png(2500, 10)),
        ];
        foreach ($rejected as $case => $file) {
            $response = $this->post('/admin/settings', $this->settingsForm(), files: ['logo' => $file]);
            $this->assertStatus(422, $response);
            $this->assertStringContainsString('field-error', $response->body(), $case);
        }
        $this->assertSame($this->png(), $this->get('/media/logo')->body(), 'rejected uploads leave the old logo');

        $this->post('/admin/settings', $this->settingsForm(['remove_logo' => '1']));
        $this->assertStatus(404, $this->get('/media/logo'));
        $this->assertSame(1, $this->auditCount('settings.logo_uploaded'));
        $this->assertSame(1, $this->auditCount('settings.logo_removed'));
    }

    public function testMaintenanceModeBlocksVisitorsButNotAdminOrHealth(): void
    {
        $this->post('/admin/settings', $this->settingsForm(['maintenance_mode' => '1']));
        $adminCookies = $this->cookies;

        $this->assertStatus(200, $this->get('/'), 'signed-in admin can still browse');

        $this->cookies = [];
        $visitor = $this->get('/');
        $this->assertStatus(503, $visitor);
        $this->assertSame('3600', $visitor->header('Retry-After'));
        $this->assertStringContainsString('back soon', $visitor->body());
        $this->assertStatus(200, $this->get('/health'));
        $this->assertStatus(200, $this->get('/admin/login'));

        $this->cookies = $adminCookies;
        $this->post('/admin/settings', $this->settingsForm());
        $this->cookies = [];
        $this->assertStatus(200, $this->get('/'));
    }
}
