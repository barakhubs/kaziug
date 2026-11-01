<?php

declare(strict_types=1);

namespace Tests\Http;

use Tests\Support\AdminHttpTestCase;

final class AdminCompaniesTest extends AdminHttpTestCase
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

    /** @return array<string, mixed> a $_FILES-style entry */
    private function upload(string $bytes): array
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'kz');
        file_put_contents($path, $bytes);
        $this->tempFiles[] = $path;

        return ['name' => 'logo.png', 'type' => 'image/png', 'tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => strlen($bytes)];
    }

    private function png(int $width = 40): string
    {
        $image = imagecreatetruecolor($width, 20);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    /** Posts the "+ company" dialog the way admin.js does. @return array<string, mixed> */
    private function addViaDialog(array $data, ?string $logo): array
    {
        $this->headers['accept'] = 'application/json';
        $response = $this->post('/admin/companies', $data, files: $logo === null ? [] : ['logo' => $this->upload($logo)]);
        unset($this->headers['accept']);

        return ['status' => $response->status(), 'json' => json_decode($response->body(), true)];
    }

    /** @return array<string, string> */
    private function jobForm(array $overrides = []): array
    {
        $this->post('/admin/categories', ['name' => 'Technology', 'is_active' => '1']);

        return [...[
            'title' => 'Data Analyst',
            'location' => 'Kampala',
            'job_type' => 'full-time',
            'experience_level' => 'mid',
            'category_id' => (string) $this->db->fetchValue("SELECT id FROM job_categories WHERE name = 'Technology'"),
            'summary' => 'Turn data into decisions.',
            'apply_method' => 'link',
            'apply_url' => 'https://insights.example/careers/analyst',
            'expires_at' => date('Y-m-d', strtotime('+14 days')),
            'intent' => 'publish',
        ], ...$overrides];
    }

    public function testDialogCreatesACompanyWithARequiredLogo(): void
    {
        $missing = $this->addViaDialog(['name' => 'Kampala Insights'], null);
        $this->assertSame(422, $missing['status']);
        $this->assertSame('Upload the company logo.', $missing['json']['errors']['logo']);

        $notImage = $this->addViaDialog(['name' => 'Kampala Insights'], "<?php echo 'x';");
        $this->assertSame(422, $notImage['status']);
        $this->assertArrayHasKey('logo', $notImage['json']['errors']);

        $badSite = $this->addViaDialog(['name' => 'Kampala Insights', 'website' => 'insights.example'], $this->png());
        $this->assertArrayHasKey('website', $badSite['json']['errors']);
        $this->assertSame(0, (int) $this->db->fetchValue('SELECT count(*) FROM companies'));

        $created = $this->addViaDialog(['name' => '  Kampala   Insights ', 'website' => 'https://insights.example'], $this->png());
        $this->assertSame(201, $created['status']);
        $company = $created['json']['company'];
        $this->assertSame('Kampala Insights', $company['name']);
        $this->assertSame("/media/companies/{$company['id']}/logo?v=1", $company['logoUrl']);
        $this->assertSame(1, $this->auditCount('company.created'));

        $logo = $this->get($company['logoUrl']);
        $this->assertStatus(200, $logo);
        $this->assertSame('image/png', $logo->header('Content-Type'));
        $this->assertSame($this->png(), $logo->body());
        $this->assertStringContainsString('immutable', (string) $logo->header('Cache-Control'));

        $duplicate = $this->addViaDialog(['name' => 'KAMPALA INSIGHTS'], $this->png());
        $this->assertSame(422, $duplicate['status']);
        $this->assertArrayHasKey('name', $duplicate['json']['errors']);
    }

    public function testPickerSearchListsMatchesWithPrefixMatchesFirst(): void
    {
        foreach (['Bank of Uganda', 'Uganda Revenue Authority', 'MTN Uganda'] as $name) {
            $this->db->insert('INSERT INTO companies (name) VALUES (?)', [$name]);
        }

        $response = $this->get('/admin/companies/search', ['q' => 'uganda']);
        $this->assertStatus(200, $response);
        $names = array_column(json_decode($response->body(), true)['companies'], 'name');
        $this->assertSame('Uganda Revenue Authority', $names[0]);
        $this->assertEqualsCanonicalizing(['Bank of Uganda', 'Uganda Revenue Authority', 'MTN Uganda'], $names);
        $this->assertSame([], json_decode($this->get('/admin/companies/search', ['q' => 'zzz'])->body(), true)['companies']);

        $this->cookies = [];
        $this->assertRedirect('/admin/login', $this->get('/admin/companies/search', ['q' => 'uganda']));
    }

    public function testFullFormEditRenameLogoReplaceAndBlockedDelete(): void
    {
        $this->assertStatus(200, $this->get('/admin/companies/new'));
        $this->assertRedirect('/admin/companies', $this->post('/admin/companies', ['name' => 'Savanna Labs'], files: ['logo' => $this->upload($this->png())]));
        $id = (int) $this->db->fetchValue("SELECT id FROM companies WHERE name = 'Savanna Labs'");
        $this->assertStringContainsString('Savanna Labs', $this->get('/admin/companies')->body());

        $this->post('/admin/jobs', $this->jobForm(['company_id' => (string) $id, 'title' => 'Frontend Developer']));
        $this->assertStringContainsString('Frontend Developer', $this->get('/', ['q' => 'savanna'])->body());

        // Renaming keeps the logo and makes the job findable under the new name.
        $this->assertRedirect('/admin/companies', $this->post("/admin/companies/{$id}", ['name' => 'Savanna Tech Labs']));
        $this->assertSame(1, (int) $this->db->fetchValue('SELECT logo_version FROM companies WHERE id = ?', [$id]));
        $this->assertStringContainsString('Frontend Developer', $this->get('/', ['q' => 'tech labs'])->body());

        $this->post("/admin/companies/{$id}", ['name' => 'Savanna Tech Labs'], files: ['logo' => $this->upload($this->png(60))]);
        $this->assertSame(2, (int) $this->db->fetchValue('SELECT logo_version FROM companies WHERE id = ?', [$id]));
        $this->assertSame(2, $this->auditCount('company.updated'));

        $this->post("/admin/companies/{$id}/delete");
        $this->assertNotNull($this->db->fetchValue('SELECT id FROM companies WHERE id = ?', [$id]), 'companies with jobs stay');

        $spare = $this->db->insert("INSERT INTO companies (name) VALUES ('Spare Co')");
        $this->post("/admin/companies/{$spare}/delete");
        $this->assertNull($this->db->fetchValue('SELECT id FROM companies WHERE id = ?', [$spare]));
        $this->assertSame(1, $this->auditCount('company.deleted'));
    }

    public function testCompanyWithoutALogoMustGetOneWhenEdited(): void
    {
        $id = $this->db->insert("INSERT INTO companies (name) VALUES ('Imported Co')");

        $response = $this->post("/admin/companies/{$id}", ['name' => 'Imported Co']);
        $this->assertStatus(422, $response);
        $this->assertStringContainsString('Upload the company logo.', $response->body());
    }

    public function testJobFormPicksCompaniesByIdOrExactName(): void
    {
        $id = $this->db->insert("INSERT INTO companies (name, website) VALUES ('Kampala Insights', 'https://insights.example')");

        // Without JavaScript only the typed name arrives; an exact name (any case) still matches.
        $this->post('/admin/jobs', $this->jobForm(['company_name' => 'kampala insights']));
        $this->assertSame($id, (int) $this->db->fetchValue('SELECT company_id FROM jobs'));

        $unknown = $this->post('/admin/jobs', $this->jobForm(['title' => 'Other', 'company_name' => 'Nobody Ltd']));
        $this->assertStatus(422, $unknown);
        $this->assertStringContainsString('Choose a company from the list, or add a new one with +.', $unknown->body());
        $this->assertStringContainsString('value="Nobody Ltd"', $unknown->body());

        $form = $this->get('/admin/jobs/new')->body();
        $this->assertStringContainsString('data-company-picker', $form);
        $this->assertStringContainsString('<dialog id="companyDialog"', $form);
    }

    public function testPublicPagesShowTheLogoOrInitialsAndStructuredData(): void
    {
        $created = $this->addViaDialog(['name' => 'Kampala Insights', 'website' => 'https://insights.example'], $this->png());
        $logoUrl = $created['json']['company']['logoUrl'];
        $plain = $this->db->insert("INSERT INTO companies (name) VALUES ('Gulu Water Works')");
        $this->post('/admin/jobs', $this->jobForm(['company_id' => (string) $created['json']['company']['id']]));
        $this->post('/admin/jobs', $this->jobForm(['title' => 'Plumber', 'company_id' => (string) $plain]));

        $home = $this->get('/')->body();
        $this->assertStringContainsString('<img class="company-logo sm" src="' . $logoUrl . '"', $home);
        $this->assertStringContainsString('<span class="company-logo initials sm" aria-hidden="true">GW</span>', $home);

        $slug = (string) $this->db->fetchValue("SELECT slug FROM jobs WHERE title = 'Data Analyst'");
        $page = $this->get("/jobs/{$slug}")->body();
        $this->assertStringContainsString('class="company-logo lg"', $page);
        preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $page, $m);
        $org = json_decode($m[1], true)['hiringOrganization'];
        $this->assertSame('https://insights.example', $org['sameAs']);
        $this->assertStringEndsWith($logoUrl, $org['logo']);
    }
}
