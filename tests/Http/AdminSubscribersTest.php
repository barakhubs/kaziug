<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\AdminHttpTestCase;

final class AdminSubscribersTest extends AdminHttpTestCase
{
    /** @var list<string> */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $rows = [
            ['amina@example.com', 'Amina', 'active'],
            ['brian@example.com', '=HYPERLINK("http://evil")', 'active'],
            ['carol@example.com', null, 'pending'],
            ['dan@example.com', 'Dan', 'unsubscribed'],
        ];
        foreach ($rows as $i => [$email, $name, $status]) {
            $this->db->execute(
                "INSERT INTO subscribers (email, name, status, consent_at, consent_ip, unsubscribe_token) VALUES (?, ?, ?, now(), '10.0.0.1', ?)",
                [$email, $name, $status, str_pad((string) $i, 43, 'u')],
            );
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function csv(string $content): array
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'kzcsv');
        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return ['name' => 'list.csv', 'type' => 'text/csv', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content)];
    }

    public function testListFiltersAndSearch(): void
    {
        $all = $this->get('/admin/subscribers')->body();
        $this->assertStringContainsString('of <strong>4</strong> subscribers', $all);

        $active = $this->get('/admin/subscribers', ['status' => 'active'])->body();
        $this->assertStringContainsString('amina@example.com', $active);
        $this->assertStringNotContainsString('carol@example.com', $active);

        $search = $this->get('/admin/subscribers', ['q' => 'dan'])->body();
        $this->assertStringContainsString('of <strong>1</strong> subscriber', $search);
    }

    public function testExportCsvIsFilteredAndSafe(): void
    {
        $response = $this->get('/admin/subscribers/export.csv', ['status' => 'active']);
        $body = $response->body(); // the body streams, so read it once

        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('text/csv', (string) $response->header('Content-Type'));
        $this->assertStringContainsString('attachment; filename="subscribers-active-', (string) $response->header('Content-Disposition'));
        $lines = array_values(array_filter(explode("\n", $body)));
        $this->assertSame('email,name,status,consent_at,consent_ip,confirmed_at,created_at,unsubscribed_at', $lines[0]);
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('"\'=HYPERLINK(""http://evil"")"', $body);
        $this->assertSame(1, $this->auditCount('subscribers.exported'));
    }

    public function testImportRequiresConsentAndReportsRows(): void
    {
        $file = "email,name\nnew1@example.com,New One\nAMINA@example.com,Dup Existing\nnew1@example.com,Dup In File\nbroken,Bad\nnew2@example.com,\n";

        $noConsent = $this->post('/admin/subscribers/import', [], files: ['csv' => $this->csv($file)]);
        $this->assertStatus(422, $noConsent);
        $this->assertSame(4, (int) $this->db->fetchValue('SELECT count(*) FROM subscribers'));

        $report = $this->post('/admin/subscribers/import', ['consent' => '1'], files: ['csv' => $this->csv($file)]);
        $this->assertStatus(200, $report);
        $body = $report->body();
        $this->assertMatchesRegularExpression('#<dt>Imported</dt><dd><strong>2</strong>#', $body);
        $this->assertMatchesRegularExpression('#Already subscribed \(skipped\)</dt><dd>1<#', $body);
        $this->assertMatchesRegularExpression('#Duplicates in the file</dt><dd>1<#', $body);
        $this->assertStringContainsString('Line 5: Not a valid email address.', $body);

        $this->assertSame('active', $this->db->fetchValue("SELECT status FROM subscribers WHERE email = 'new1@example.com'"));
        $this->assertSame('Amina', $this->db->fetchValue("SELECT name FROM subscribers WHERE email = 'amina@example.com'"), 'existing rows untouched');
        $this->assertSame(1, $this->auditCount('subscribers.imported'));
    }

    public function testImportRejectsMissingOrNonUtf8File(): void
    {
        $this->assertStatus(422, $this->post('/admin/subscribers/import', ['consent' => '1']));
        $this->assertStatus(422, $this->post('/admin/subscribers/import', ['consent' => '1'], files: ['csv' => $this->csv("caf\xE9@example.com\n")]));
    }

    public function testUnsubscribeAndPrivacyDelete(): void
    {
        $amina = (int) $this->db->fetchValue("SELECT id FROM subscribers WHERE email = 'amina@example.com'");

        $this->post("/admin/subscribers/{$amina}/unsubscribe");
        $this->assertSame('unsubscribed', $this->db->fetchValue('SELECT status FROM subscribers WHERE id = ?', [$amina]));

        $this->post("/admin/subscribers/{$amina}/delete");
        $this->assertNull($this->db->fetchValue('SELECT id FROM subscribers WHERE id = ?', [$amina]));

        $audit = (string) $this->db->fetchValue("SELECT metadata::text FROM audit_logs WHERE action = 'subscriber.deleted'");
        $this->assertStringContainsString('a***@example.com', $audit);
        $this->assertStringNotContainsString('amina@example.com', $audit);
    }
}
