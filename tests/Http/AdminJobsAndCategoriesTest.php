<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Console\Commands\SeedCommand;
use App\Console\Io;
use Tests\Support\AdminHttpTestCase;

final class AdminJobsAndCategoriesTest extends AdminHttpTestCase
{
    private function categoryId(string $name = 'Technology'): int
    {
        $id = $this->db->fetchValue('SELECT id FROM job_categories WHERE name = ?', [$name]);
        if ($id === null) {
            $this->post('/admin/categories', ['name' => $name, 'is_active' => '1']);
            $id = $this->db->fetchValue('SELECT id FROM job_categories WHERE name = ?', [$name]);
        }

        return (int) $id;
    }

    /** @return array<string, string> */
    private function jobForm(array $overrides = []): array
    {
        return [...[
            'title' => 'Data Analyst',
            'company' => 'Kampala Insights',
            'location' => 'Kampala',
            'job_type' => 'full-time',
            'experience_level' => 'mid',
            'category_id' => (string) $this->categoryId(),
            'salary_min' => '3000000',
            'salary_max' => '4500000',
            'summary' => 'Turn data into decisions.',
            'responsibilities' => "Build dashboards\nClean data",
            'requirements' => 'SQL',
            'skills' => 'SQL, Python',
            'apply_method' => 'link',
            'apply_url' => 'https://insights.example/careers/analyst',
            'expires_at' => date('Y-m-d', strtotime('+14 days')),
        ], ...$overrides];
    }

    public function testAdminPagesRender(): void
    {
        $this->container->get(SeedCommand::class)->run(['--only=jobs'], new Io([], echo: false));
        $jobId = (int) $this->db->fetchValue('SELECT min(id) FROM jobs');
        $categoryId = (int) $this->db->fetchValue('SELECT min(id) FROM job_categories');

        foreach (['/admin', '/admin/jobs', '/admin/jobs/new', "/admin/jobs/{$jobId}/edit", "/admin/jobs/{$jobId}", '/admin/categories',
            '/admin/categories/new', "/admin/categories/{$categoryId}/edit", '/admin/jobs?status=published&q=dev&page=2'] as $path) {
            $this->assertStatus(200, $this->get($path));
        }
        $this->assertStatus(404, $this->get('/admin/jobs/999999/edit'));
        $this->assertStringContainsString('Frontend Developer (React)', $this->get('/admin/jobs')->body());
    }

    public function testJobTableSortsPagesAndSummarises(): void
    {
        $this->container->get(SeedCommand::class)->run(['--only=jobs'], new Io([], echo: false));

        $page = $this->get('/admin/jobs', ['sort' => 'title', 'dir' => 'asc', 'per_page' => '10'])->body();
        $this->assertStringContainsString('Showing <strong>1–10</strong> of <strong>14</strong> jobs', $page);
        preg_match_all('#<strong><a href="/admin/jobs/\d+">([^<]+)</a></strong>#', $page, $m);
        $titles = array_map('html_entity_decode', $m[1]);
        $this->assertCount(10, $titles);
        $this->assertSame('Agronomist', $titles[0]);
        $sorted = $titles;
        sort($sorted, SORT_STRING | SORT_FLAG_CASE);
        $this->assertSame($sorted, $titles);
        $this->assertStringContainsString('href="/admin/jobs?sort=title&amp;dir=asc&amp;per_page=10&amp;page=2"', $page);
        $this->assertStringContainsString('aria-sort="ascending"', $page);

        $page2 = $this->get('/admin/jobs', ['sort' => 'title', 'dir' => 'asc', 'per_page' => '10', 'page' => '2'])->body();
        $this->assertStringContainsString('Showing <strong>11–14</strong> of <strong>14</strong> jobs', $page2);

        // Unknown sort keys and page sizes fall back to the defaults.
        $this->assertStringContainsString('Showing <strong>1–14</strong>', $this->get('/admin/jobs', ['sort' => 'id; DROP', 'per_page' => '7'])->body());
    }

    public function testJobDetailsPageShowsEverything(): void
    {
        $this->post('/admin/jobs', $this->jobForm(['intent' => 'publish', 'apply_method' => 'both', 'apply_email' => 'hr@insights.example']));
        $id = (int) $this->db->fetchValue('SELECT id FROM jobs');

        $body = $this->get("/admin/jobs/{$id}")->body();

        $this->assertStringContainsString('Kampala Insights', $body);
        $this->assertStringContainsString('https://insights.example/careers/analyst', $body);
        $this->assertStringContainsString('hr@insights.example', $body);
        $this->assertStringContainsString('Build dashboards', $body);
        $this->assertStringContainsString('Created as published', $body);

        $public = $this->get('/jobs/' . $this->db->fetchValue('SELECT slug FROM jobs'))->body();
        $this->assertStringContainsString('Apply / more info', $public);
        $this->assertStringContainsString('mailto:hr@insights.example', $public);
    }

    public function testGovernmentJobWithSalaryScaleAndPositions(): void
    {
        $this->post('/admin/jobs', $this->jobForm([
            'intent' => 'publish', 'salary_min' => '', 'salary_max' => '', 'salary_scale' => 'U4', 'positions' => '3',
        ]));
        $id = (int) $this->db->fetchValue('SELECT max(id) FROM jobs');
        $slug = (string) $this->db->fetchValue('SELECT slug FROM jobs WHERE id = ?', [$id]);

        $admin = $this->get("/admin/jobs/{$id}")->body();
        $this->assertStringContainsString('<dt>Salary</dt><dd>Salary scale U4</dd>', $admin);
        $this->assertStringContainsString('<dt>Positions</dt><dd>3</dd>', $admin);
        $this->assertStringContainsString('name="salary_scale" value="U4"', $this->get("/admin/jobs/{$id}/edit")->body());

        $this->assertStringContainsString('3 positions', $this->get('/')->body());
        $page = $this->get("/jobs/{$slug}")->body();
        $this->assertStringContainsString('Salary scale U4', $page);
        $this->assertStringContainsString('Salary: Salary scale U4. 3 positions available.', $page);
        preg_match('#<script type="application/ld\+json">(\{"@context":"https://schema.org/","@type":"JobPosting".+?)</script>#s', $page, $m);
        $schema = json_decode($m[1] ?? '{}', true);
        $this->assertSame(3, $schema['totalJobOpenings'] ?? null);
        $this->assertArrayNotHasKey('baseSalary', $schema);
        $this->assertStringContainsString('Salary scale:</strong> U4', (string) ($schema['description'] ?? ''));

        $both = $this->post("/admin/jobs/{$id}", $this->jobForm(['salary_scale' => 'U4']));
        $this->assertStatus(422, $both);
        $this->assertStringContainsString('Give either a salary amount or a salary scale, not both.', $both->body());
    }

    public function testApplicationInstructionsAreSavedEscapedAndShown(): void
    {
        $text = "Attach copies of your <b>certificates</b>.\nDeliver hard copies to the HR office.";
        $this->post('/admin/jobs', $this->jobForm(['intent' => 'publish', 'application_instructions' => $text]));
        $id = (int) $this->db->fetchValue('SELECT max(id) FROM jobs');
        $slug = (string) $this->db->fetchValue('SELECT slug FROM jobs WHERE id = ?', [$id]);
        $escaped = 'Attach copies of your &lt;b&gt;certificates&lt;/b&gt;.' . "\n" . 'Deliver hard copies to the HR office.';

        $this->assertStringContainsString('<dd class="apply-instructions">' . $escaped . '</dd>', $this->get("/admin/jobs/{$id}")->body());
        $this->assertStringContainsString('>' . $escaped . '</textarea>', $this->get("/admin/jobs/{$id}/edit")->body());
        $page = $this->get("/jobs/{$slug}")->body();
        $this->assertStringContainsString('<p class="apply-instructions">' . $escaped . '</p>', $page);
        $this->assertStringNotContainsString('<b>certificates', $page);

        $this->post("/admin/jobs/{$id}", $this->jobForm(['application_instructions' => '']));
        $this->assertNull($this->db->fetchValue('SELECT application_instructions FROM jobs WHERE id = ?', [$id]));
    }

    public function testReportsToIsOptionalAndShown(): void
    {
        $this->post('/admin/jobs', $this->jobForm(['intent' => 'publish', 'reports_to' => '  Consultant –   Adult <Cardiology> ']));
        $id = (int) $this->db->fetchValue('SELECT max(id) FROM jobs');
        $slug = (string) $this->db->fetchValue('SELECT slug FROM jobs WHERE id = ?', [$id]);
        $this->assertSame('Consultant – Adult <Cardiology>', $this->db->fetchValue('SELECT reports_to FROM jobs WHERE id = ?', [$id]));

        $escaped = 'Consultant – Adult &lt;Cardiology&gt;';
        $this->assertStringContainsString('<dt>Reports to</dt><dd>' . $escaped . '</dd>', $this->get("/admin/jobs/{$id}")->body());
        $this->assertStringContainsString('name="reports_to" value="' . $escaped . '"', $this->get("/admin/jobs/{$id}/edit")->body());
        $this->assertStringContainsString('<strong>Reports to:</strong> ' . $escaped, $this->get("/jobs/{$slug}")->body());

        $tooLong = $this->post("/admin/jobs/{$id}", $this->jobForm(['reports_to' => str_repeat('a', 151)]));
        $this->assertStatus(422, $tooLong);

        $this->post("/admin/jobs/{$id}", $this->jobForm(['reports_to' => '']));
        $this->assertNull($this->db->fetchValue('SELECT reports_to FROM jobs WHERE id = ?', [$id]));
        $this->assertStringNotContainsString('Reports to:', $this->get("/jobs/{$slug}")->body());
    }

    public function testDeadlineIsRequiredAndShownEverywhere(): void
    {
        $missing = $this->post('/admin/jobs', $this->jobForm(['expires_at' => '', 'intent' => 'publish']));
        $this->assertStatus(422, $missing);
        $this->assertStringContainsString('Set the application deadline.', $missing->body());

        $deadline = date('Y-m-d', strtotime('+2 days'));
        $this->post('/admin/jobs', $this->jobForm(['expires_at' => $deadline, 'intent' => 'publish']));
        $id = (int) $this->db->fetchValue('SELECT id FROM jobs');
        $slug = (string) $this->db->fetchValue('SELECT slug FROM jobs');

        $this->assertStringContainsString('Closes in 2 days', $this->get('/admin/jobs')->body());
        $this->assertStringContainsString('Closes in 2 days', $this->get("/admin/jobs/{$id}")->body());

        $page = $this->get("/jobs/{$slug}")->body();
        $this->assertStringContainsString('Application deadline', $page);
        $this->assertStringContainsString('<time datetime="' . $deadline . '">', $page);
        $this->assertStringContainsString('deadline-box soon', $page);
        preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $page, $m);
        $this->assertStringStartsWith($deadline . 'T23:59:59', json_decode($m[1], true)['validThrough']);
        $this->assertStringContainsString('Closes in 2 days', $this->get('/')->body(), 'job cards show it too');
    }

    public function testJobStaysListedUntilTheEndOfItsDeadlineDay(): void
    {
        $this->post('/admin/jobs', $this->jobForm(['expires_at' => date('Y-m-d'), 'intent' => 'publish']));
        $slug = (string) $this->db->fetchValue('SELECT slug FROM jobs');

        $this->assertStatus(200, $this->get("/jobs/{$slug}"));
        $this->assertStringContainsString('Closes today', $this->get("/jobs/{$slug}")->body());

        $this->db->execute("UPDATE jobs SET expires_at = now() - interval '1 second'");
        $this->assertStatus(404, $this->get("/jobs/{$slug}"));
    }

    public function testJobCannotBeSavedWithoutApplicationDetails(): void
    {
        $response = $this->post('/admin/jobs', $this->jobForm(['apply_method' => 'email', 'apply_email' => '']));

        $this->assertStatus(422, $response);
        $this->assertStringContainsString('Enter the email address applications go to.', $response->body());
        $this->assertSame(0, (int) $this->db->fetchValue('SELECT count(*) FROM jobs'));
    }

    public function testAdminRoutesRequireLogin(): void
    {
        $this->post('/admin/logout');
        $this->assertRedirect('/admin/login', $this->get('/admin/jobs'));
        $this->assertRedirect('/admin/login', $this->post('/admin/jobs', $this->jobForm()));
        $this->assertSame(0, (int) $this->db->fetchValue('SELECT count(*) FROM jobs'));
    }

    public function testCreateJobValidationErrorsKeepInput(): void
    {
        $response = $this->post('/admin/jobs', $this->jobForm(['title' => '', 'salary_max' => '10']));

        $this->assertStatus(422, $response);
        $this->assertStringContainsString('This field is required.', $response->body());
        $this->assertStringContainsString('value="Kampala Insights"', $response->body());
    }

    public function testCreateEditPublishCloseArchive(): void
    {
        $response = $this->post('/admin/jobs', $this->jobForm(['intent' => 'draft']));
        $id = (int) $this->db->fetchValue('SELECT id FROM jobs');
        $this->assertRedirect("/admin/jobs/{$id}", $response);
        $this->assertSame('draft', $this->db->fetchValue('SELECT status FROM jobs WHERE id = ?', [$id]));

        $this->assertRedirect("/admin/jobs/{$id}", $this->post("/admin/jobs/{$id}", $this->jobForm(['title' => 'Senior Data Analyst'])));
        $this->assertSame('Senior Data Analyst', $this->db->fetchValue('SELECT title FROM jobs WHERE id = ?', [$id]));

        foreach (['published', 'closed', 'archived'] as $to) {
            $this->post("/admin/jobs/{$id}/status", ['to' => $to]);
            $this->assertSame($to, $this->db->fetchValue('SELECT status FROM jobs WHERE id = ?', [$id]));
        }
        $this->assertSame(4, (int) $this->db->fetchValue('SELECT count(*) FROM job_status_history WHERE job_id = ?', [$id]));

        $edit = $this->get("/admin/jobs/{$id}")->body();
        $this->assertStringContainsString('closed → archived', $edit);
        $this->assertStringContainsString(self::ADMIN_EMAIL, $edit);

        // Archived is final.
        $this->post("/admin/jobs/{$id}/status", ['to' => 'published']);
        $this->assertSame('archived', $this->db->fetchValue('SELECT status FROM jobs WHERE id = ?', [$id]));
        $this->assertStringContainsString("can&apos;t be moved", $this->get("/admin/jobs/{$id}")->body());
        $this->assertSame(1, $this->auditCount('job.created'));
        $this->assertSame(3, $this->auditCount('job.status_changed'));
    }

    public function testBulkStatusDuplicateAndSoftDelete(): void
    {
        $this->post('/admin/jobs', $this->jobForm(['intent' => 'draft', 'title' => 'One']));
        $this->post('/admin/jobs', $this->jobForm(['intent' => 'draft', 'title' => 'Two']));
        $this->post('/admin/jobs', $this->jobForm(['intent' => 'publish', 'title' => 'Three']));
        $ids = array_map('intval', array_column($this->db->fetchAll('SELECT id FROM jobs ORDER BY id'), 'id'));

        $response = $this->post('/admin/jobs/bulk-status', ['ids' => array_map('strval', $ids), 'to' => 'published', 'return_to' => '/admin/jobs?status=draft']);
        $this->assertRedirect('/admin/jobs?status=draft', $response);
        $this->assertSame(3, (int) $this->db->fetchValue("SELECT count(*) FROM jobs WHERE status = 'published'"));
        $this->assertStringContainsString('1 skipped', $this->get('/admin/jobs')->body());

        $dup = $this->post("/admin/jobs/{$ids[0]}/duplicate");
        $copyId = (int) $this->db->fetchValue('SELECT max(id) FROM jobs');
        $this->assertRedirect("/admin/jobs/{$copyId}/edit", $dup);
        $this->assertSame('draft', $this->db->fetchValue('SELECT status FROM jobs WHERE id = ?', [$copyId]));

        $this->post("/admin/jobs/{$ids[1]}/delete", ['return_to' => 'https://evil.example/']);
        $this->assertNotNull($this->db->fetchValue('SELECT deleted_at FROM jobs WHERE id = ?', [$ids[1]]));
        $this->assertStringNotContainsString('>Two<', $this->get('/admin/jobs')->body());
        $this->assertStatus(404, $this->get("/admin/jobs/{$ids[1]}/edit"));
    }

    public function testCategoryCrudReorderAndBlockedDelete(): void
    {
        $this->assertRedirect('/admin/categories', $this->post('/admin/categories', ['name' => 'Engineering', 'is_active' => '1']));
        $this->post('/admin/categories', ['name' => 'Design', 'is_active' => '1']);
        $eng = $this->categoryId('Engineering');
        $design = $this->categoryId('Design');
        $this->assertSame('engineering', $this->db->fetchValue('SELECT slug FROM job_categories WHERE id = ?', [$eng]));

        $dupSlug = $this->post("/admin/categories/{$design}", ['name' => 'Design', 'slug' => 'engineering']);
        $this->assertStatus(422, $dupSlug);

        $this->post("/admin/categories/{$design}/move", ['direction' => 'up']);
        $order = array_column($this->db->fetchAll('SELECT name FROM job_categories ORDER BY sort_order'), 'name');
        $this->assertSame(['Design', 'Engineering'], $order);

        $this->post("/admin/categories/{$eng}/toggle", ['active' => '0']);
        $this->assertFalse((bool) $this->db->fetchValue('SELECT is_active FROM job_categories WHERE id = ?', [$eng]));

        $this->post('/admin/jobs', $this->jobForm(['category_id' => (string) $eng, 'intent' => 'draft']));
        $jobId = (int) $this->db->fetchValue('SELECT id FROM jobs');
        $this->post("/admin/jobs/{$jobId}/delete");

        $blocked = $this->post("/admin/categories/{$eng}/delete");
        $this->assertRedirect('/admin/categories', $blocked);
        $this->assertNotNull($this->db->fetchValue('SELECT id FROM job_categories WHERE id = ?', [$eng]));
        $this->assertStringContainsString('can&apos;t be deleted', $this->get('/admin/categories')->body());

        $this->post("/admin/categories/{$design}/delete");
        $this->assertNull($this->db->fetchValue('SELECT id FROM job_categories WHERE id = ?', [$design]));
        $this->assertSame(1, $this->auditCount('category.deleted'));
    }

    public function testOutputIsEscaped(): void
    {
        $this->post('/admin/jobs', $this->jobForm(['title' => '<script>alert(1)</script>', 'intent' => 'draft']));

        $body = $this->get('/admin/jobs')->body();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }

    public function testSeedIsIdempotent(): void
    {
        $seed = $this->container->get(SeedCommand::class);
        $seed->run(['--only=jobs'], new Io([], echo: false));
        $seed->run(['--only=jobs'], new Io([], echo: false));

        $this->assertSame(14, (int) $this->db->fetchValue('SELECT count(*) FROM jobs'));
        $this->assertSame(10, (int) $this->db->fetchValue('SELECT count(*) FROM job_categories'));
        $this->assertSame(14, (int) $this->db->fetchValue("SELECT count(*) FROM jobs WHERE status = 'published'"));
    }
}
