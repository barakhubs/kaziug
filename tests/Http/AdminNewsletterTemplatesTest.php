<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Console\Commands\SeedCommand;
use App\Console\Io;
use Tests\Support\AdminHttpTestCase;

final class AdminNewsletterTemplatesTest extends AdminHttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->container->get(SeedCommand::class)->run(['--only=jobs'], new Io([], echo: false));
        $this->db->execute("UPDATE settings SET value = '\"Plot 5, Kampala\"' WHERE `key` = 'physical_address'");
    }

    public function testPickerOffersTemplatesAndTheNewestLiveJobsWithEightTicked(): void
    {
        $draft = $this->jobId('Senior Accountant');
        $this->db->execute("UPDATE jobs SET status = 'draft', published_at = NULL WHERE id = ?", [$draft]);

        $body = $this->get('/admin/newsletters/new')->body();
        foreach (['Latest jobs', 'Job spotlight', 'Announcement', 'CV help promo'] as $name) {
            $this->assertStringContainsString("<h2>{$name}</h2>", $body);
        }
        $this->assertStringNotContainsString('Senior Accountant', $body, 'only live jobs are offered');
        $this->assertSame(8, preg_match_all('/type="checkbox" name="jobs\[\]" value="\d+" checked/', $body));
        $this->assertStringContainsString('href="/admin/newsletters/new?blank=1"', $body);
        $this->assertStringContainsString('name="body_html"', $this->get('/admin/newsletters/new', ['blank' => '1'])->body());
    }

    public function testLatestJobsTemplateFillsTheEditorWithoutSaving(): void
    {
        $a = $this->jobId('Agronomist');
        $b = $this->jobId('Data Analyst');

        $body = html_entity_decode($this->post('/admin/newsletters/new', ['template' => 'latest-jobs', 'jobs' => [(string) $a, (string) $b]])->body());

        $this->assertStringContainsString('value="2 new jobs on KaziUG"', $body);
        $this->assertStringContainsString('<a href="http://localhost/jobs/' . $this->slug('Agronomist') . '">Agronomist</a>', $body);
        $this->assertStringContainsString('Data Analyst', $body);
        $this->assertStringNotContainsString('Frontend Developer', $body, 'unticked jobs are left out');
        $this->assertStringContainsString('data-live-preview', $body);
        $this->assertSame(0, (int) $this->db->fetchValue('SELECT count(*) FROM newsletters'), 'nothing saved yet');
    }

    public function testJobSelectionIsValidated(): void
    {
        $agronomist = $this->jobId('Agronomist');
        $analyst = $this->jobId('Data Analyst');
        $this->db->execute("UPDATE jobs SET status = 'closed' WHERE id = ?", [$analyst]);

        $this->assertStatus(422, $this->post('/admin/newsletters/new', ['template' => 'latest-jobs', 'jobs' => [(string) $analyst]]));
        $this->assertStatus(422, $this->post('/admin/newsletters/new', ['template' => 'latest-jobs']));
        $this->assertStatus(422, $this->post('/admin/newsletters/new', ['template' => 'job-spotlight', 'jobs' => [(string) $agronomist, (string) $this->jobId('Registered Nurse')]]));
        $this->assertStatus(422, $this->post('/admin/newsletters/new', ['template' => 'nope']));

        $spotlight = html_entity_decode($this->post('/admin/newsletters/new', ['template' => 'job-spotlight', 'jobs' => [(string) $agronomist]])->body());
        $this->assertStringContainsString('value="Job opening: Agronomist at', $spotlight);
        $this->assertStatus(200, $this->post('/admin/newsletters/new', ['template' => 'announcement']));
        $this->assertStatus(200, $this->post('/admin/newsletters/new', ['template' => 'cv-help']));
        $this->assertSame(0, (int) $this->db->fetchValue("SELECT count(*) FROM audit_logs WHERE action LIKE 'newsletter.%'"));
    }

    public function testLivePreviewRendersUnsavedContentSafely(): void
    {
        $preview = $this->post('/admin/newsletters/preview', [
            'subject' => 'Draft subject',
            'preheader' => 'Peek',
            'body_html' => '<h2>Hi</h2><p onclick="x()">Body</p><p><a href="https://kazi.ug/jobs">See jobs</a></p><script>alert(1)</script>',
        ]);

        $this->assertStatus(200, $preview);
        $this->assertSame('SAMEORIGIN', $preview->header('X-Frame-Options'));
        $this->assertStringContainsString("default-src 'none'", (string) $preview->header('Content-Security-Policy'));
        $html = $preview->body();
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString('onclick', $html);
        $this->assertStringContainsString('Plot 5, Kampala', $html);
        $this->assertStringContainsString('Unsubscribe', $html);
        $this->assertMatchesRegularExpression('#<a href="https://kazi.ug/jobs" style="display:inline-block;background:\#f2b233#', $html, 'a lone link is a button');
        $this->assertSame(0, (int) $this->db->fetchValue('SELECT count(*) FROM newsletters'));

        $this->assertStatus(419, $this->post('/admin/newsletters/preview', ['body_html' => '<p>x</p>'], withToken: false));
    }

    public function testATemplateDraftSavesAndSendsLikeAnyNewsletter(): void
    {
        $form = $this->post('/admin/newsletters/new', ['template' => 'latest-jobs', 'jobs' => [(string) $this->jobId('Agronomist')]])->body();
        preg_match('#<textarea id="body_html"[^>]*>(.*?)</textarea>#s', $form, $m);

        $this->post('/admin/newsletters', [
            'subject' => 'New job on KaziUG: Agronomist',
            'preheader' => 'Agronomist',
            'body_html' => html_entity_decode($m[1] ?? ''),
            'body_text' => '',
        ]);
        $saved = (string) $this->db->fetchValue('SELECT body_html FROM newsletters');
        $this->assertSame(html_entity_decode($m[1] ?? ''), $saved, 'template HTML survives the sanitiser unchanged');
        $this->assertStringContainsString('View and apply', (string) $this->db->fetchValue('SELECT body_text FROM newsletters'));
    }

    private function jobId(string $title): int
    {
        return (int) $this->db->fetchValue('SELECT id FROM jobs WHERE title = ?', [$title]);
    }

    private function slug(string $title): string
    {
        return (string) $this->db->fetchValue('SELECT slug FROM jobs WHERE title = ?', [$title]);
    }
}
