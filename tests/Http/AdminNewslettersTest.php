<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Console\Commands\NewsletterWorkCommand;
use App\Console\Io;
use Tests\Support\AdminHttpTestCase;

final class AdminNewslettersTest extends AdminHttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->db->execute("INSERT INTO smtp_settings (id, host, port, encryption, from_email) VALUES (1, 'smtp.test', 25, 'none', 'jobs@kazi.test')");
        $this->db->execute("UPDATE settings SET value = '\"Plot 5, Kampala\"' WHERE `key` = 'physical_address'");
        $this->db->execute("INSERT INTO subscribers (email, status, unsubscribe_token) VALUES ('reader@example.com', 'active', ?)", [str_repeat('r', 43)]);
    }

    private function create(array $overrides = []): int
    {
        $this->post('/admin/newsletters', [...[
            'subject' => 'Jobs this week',
            'preheader' => 'Ten new roles',
            'body_html' => '<h2>Hello</h2><p onclick="steal()">See <a href="javascript:alert(1)">this</a> and <a href="https://kazi.ug">that</a>.</p><script>alert(1)</script>',
            'body_text' => '',
        ], ...$overrides]);

        return (int) $this->db->fetchValue('SELECT max(id) FROM newsletters');
    }

    public function testCreateSanitisesAndGeneratesText(): void
    {
        $this->assertStatus(200, $this->get('/admin/newsletters/new'));
        $id = $this->create();

        $row = $this->db->fetchOne('SELECT body_html, body_text, status FROM newsletters WHERE id = ?', [$id]);
        $this->assertSame('<h2>Hello</h2><p>See <a>this</a> and <a href="https://kazi.ug">that</a>.</p>', $row['body_html']);
        $this->assertSame("Hello\n\nSee this and that (https://kazi.ug).", $row['body_text']);
        $this->assertSame('draft', $row['status']);
        $this->assertStatus(422, $this->post('/admin/newsletters', ['subject' => '', 'body_html' => '']));
    }

    public function testPreviewIsSandboxedAndShowsFooter(): void
    {
        $id = $this->create();

        $show = $this->get("/admin/newsletters/{$id}")->body();
        $this->assertStringContainsString('<iframe class="email-preview" src="/admin/newsletters/' . $id . '/preview" sandbox', $show);

        $preview = $this->get("/admin/newsletters/{$id}/preview");
        $this->assertSame('SAMEORIGIN', $preview->header('X-Frame-Options'));
        $this->assertStringContainsString("default-src 'none'", (string) $preview->header('Content-Security-Policy'));
        $this->assertStringContainsString('Plot 5, Kampala', $preview->body());
        $this->assertStringContainsString('Unsubscribe', $preview->body());
        $this->assertStringNotContainsString('<script', $preview->body());
    }

    public function testSendTestEmail(): void
    {
        $id = $this->create();

        $this->post("/admin/newsletters/{$id}/test", ['test_to' => 'me@example.org']);

        $this->assertSame(['me@example.org'], $this->mail->recipients());
        $this->assertSame('[Test] Jobs this week', $this->mail->sent[0]->subject);
        $this->assertSame('draft', $this->db->fetchValue('SELECT status FROM newsletters WHERE id = ?', [$id]));
        $this->assertSame(0, (int) $this->db->fetchValue('SELECT count(*) FROM newsletter_deliveries'));
    }

    public function testSendNowThenWorkerThenDeliveryCountsShown(): void
    {
        $id = $this->create();

        $this->assertRedirect("/admin/newsletters/{$id}", $this->post("/admin/newsletters/{$id}/send"));
        $this->assertSame([], $this->mail->sent);
        $this->assertStringContainsString('Queued for 1 active subscriber(s)', $this->get("/admin/newsletters/{$id}")->body());

        $io = new Io([], echo: false);
        $this->assertSame(0, $this->container->get(NewsletterWorkCommand::class)->run([], $io));
        $this->assertStringContainsString('sent 1', implode("\n", $io->output));

        $page = $this->get("/admin/newsletters/{$id}")->body();
        $this->assertMatchesRegularExpression('#<span>Sent</span><strong>1</strong>#', $page);
        $this->assertStringContainsString('<span class="pill sent">Sent</span>', $page);
        $this->assertRedirect("/admin/newsletters/{$id}", $this->get("/admin/newsletters/{$id}/edit"), );
        $this->assertStringContainsString('Jobs this week', $this->get('/admin/newsletters')->body());
        $this->assertSame(1, $this->auditCount('newsletter.queued'));
    }

    public function testScheduleUnscheduleAndDeleteDraft(): void
    {
        $id = $this->create();

        $this->post("/admin/newsletters/{$id}/schedule", ['scheduled_at' => '2020-01-01T10:00']);
        $this->assertSame('draft', $this->db->fetchValue('SELECT status FROM newsletters WHERE id = ?', [$id]));

        $future = (new \DateTimeImmutable('+3 days'))->format('Y-m-d\TH:i');
        $this->post("/admin/newsletters/{$id}/schedule", ['scheduled_at' => $future]);
        $this->assertSame('scheduled', $this->db->fetchValue('SELECT status FROM newsletters WHERE id = ?', [$id]));
        $this->assertStringContainsString('Scheduled for', $this->get("/admin/newsletters/{$id}")->body());

        $this->post("/admin/newsletters/{$id}/unschedule");
        $this->post("/admin/newsletters/{$id}/delete");
        $this->assertNull($this->db->fetchValue('SELECT id FROM newsletters WHERE id = ?', [$id]));
    }

    public function testCannotSendWithoutPhysicalAddress(): void
    {
        $this->db->execute("UPDATE settings SET value = '\"\"' WHERE `key` = 'physical_address'");
        $id = $this->create();

        $this->post("/admin/newsletters/{$id}/send");

        $this->assertSame('draft', $this->db->fetchValue('SELECT status FROM newsletters WHERE id = ?', [$id]));
        $this->assertStringContainsString('physical address', $this->get("/admin/newsletters/{$id}")->body());
    }
}
