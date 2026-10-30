<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Console\Commands\SeedCommand;
use App\Console\Io;
use Tests\Support\HttpTestCase;

final class PublicJobsTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->container->get(SeedCommand::class)->run(['--only=jobs'], new Io([], echo: false));
    }

    private function jobId(string $titlePrefix): int
    {
        return (int) $this->db->fetchValue('SELECT id FROM jobs WHERE title LIKE ?', [$titlePrefix . '%']);
    }

    private function slug(string $titlePrefix): string
    {
        return (string) $this->db->fetchValue('SELECT slug FROM jobs WHERE title LIKE ?', [$titlePrefix . '%']);
    }

    /** Number of job cards on a list page. */
    private function cards(string $html): int
    {
        return preg_match_all('/<li class="card[ "]/', $html);
    }

    public function testHomeListsPublishedJobsWithPagination(): void
    {
        $page1 = $this->get('/');
        $this->assertStatus(200, $page1);
        $this->assertSame(8, $this->cards($page1->body()));
        $this->assertStringContainsString('<strong>14</strong> jobs', $page1->body());
        $this->assertStringContainsString('href="/?page=2"', $page1->body());

        $page2 = $this->get('/?page=2');
        $this->assertSame(6, $this->cards($page2->body()));
        $this->assertSame(6, $this->cards($this->get('/?page=99')->body()), 'past the end shows the last page');
    }

    public function testOnlyPublishedUnexpiredUndeletedJobsAreVisible(): void
    {
        $draft = $this->jobId('Senior Accountant');
        $closed = $this->jobId('Registered Nurse');
        $expired = $this->jobId('Agronomist');
        $deleted = $this->jobId('Data Analyst');
        $this->db->execute("UPDATE jobs SET status = 'draft' WHERE id = ?", [$draft]);
        $this->db->execute("UPDATE jobs SET status = 'closed' WHERE id = ?", [$closed]);
        $this->db->execute("UPDATE jobs SET expires_at = NOW(6) - INTERVAL 1 HOUR WHERE id = ?", [$expired]);
        $this->db->execute('UPDATE jobs SET deleted_at = NOW(6) WHERE id = ?', [$deleted]);

        $body = $this->get('/', ['sort' => 'date'])->body() . $this->get('/', ['page' => '2'])->body();
        $this->assertStringContainsString('<strong>10</strong> jobs', $body);
        foreach (['Senior Accountant', 'Registered Nurse', 'Agronomist', 'Data Analyst'] as $hidden) {
            $this->assertStringNotContainsString(">{$hidden}<", $body);
        }

        foreach ([$draft, $closed, $expired, $deleted] as $id) {
            $slug = $this->db->fetchValue('SELECT slug FROM jobs WHERE id = ?', [$id]);
            $this->assertStatus(404, $this->get("/jobs/{$slug}"));
        }
        $this->assertStringNotContainsString('Senior Accountant', $this->get('/saved', ['ids' => (string) $draft])->body());
        $this->assertStringNotContainsString('Senior Accountant', $this->get('/', ['job' => $this->slug('Senior Accountant')])->body());
    }

    public function testFullTextSearchMatchesPrefixesAndRanksTitlesFirst(): void
    {
        $body = $this->get('/', ['q' => 'develop'])->body();
        $this->assertSame(1, $this->cards($body), 'prefix "develop" matches "Developer"');
        $this->assertStringContainsString('Frontend Developer (React)', $body);

        // "APIs": title of neither, but in both jobs' skills; the one with it in the summary too ranks first.
        $this->assertSame(2, $this->cards($this->get('/', ['q' => 'apis'])->body()));
        $this->assertSame('Backend Engineer (PHP / Laravel)', $this->firstTitle('/?q=apis'));

        $this->assertSame(1, $this->cards($this->get('/', ['q' => 'quickbook'])->body()), 'skills are searchable');
        $this->assertSame('Registered Nurse', $this->firstTitle('/?q=nursing'), 'other word forms match');
        $this->assertSame(0, $this->cards($this->get('/', ['q' => 'astronaut'])->body()));
        $this->assertSame(8, $this->cards($this->get('/', ['q' => 'the'])->body()), 'stop-words alone do not filter');
        // Two letters is below the FULLTEXT minimum; the fallback matches word starts ("Power BI"), not "mobile".
        $this->assertSame(1, $this->cards($this->get('/', ['q' => 'bi'])->body()), 'short words still search');
        $this->assertSame('Data Analyst', $this->firstTitle('/?q=bi+dashboards'));
        $this->assertStatus(200, $this->get('/', ['q' => "'); DROP TABLE jobs; --"]));
        $this->assertSame(14, (int) $this->db->fetchValue('SELECT count(*) FROM jobs'));
    }

    public function testFiltersCombine(): void
    {
        $this->assertSame(1, $this->cards($this->get('/', ['type' => ['internship']])->body()));
        $this->assertSame(2, $this->cards($this->get('/', ['type' => ['internship', 'contract']])->body()));
        $this->assertSame(3, $this->cards($this->get('/', ['category' => 'technology'])->body()));
        $this->assertSame(3, $this->cards($this->get('/', ['exp' => 'senior'])->body()));
        $this->assertSame(1, $this->cards($this->get('/', ['loc' => 'Entebbe', 'exp' => 'mid'])->body()));
        $remote = $this->cards($this->get('/', ['remote' => '1'])->body());
        $this->assertSame($remote, $this->cards($this->get('/', ['loc' => 'remote'])->body()));
        $this->assertSame(1, $this->cards($this->get('/', ['posted' => '1'])->body()), 'only the job posted today');

        // Ranges overlap the requested range: 6.0M+ matches 8M, 6.2M and exactly 6M maxima.
        $rich = $this->get('/', ['salary_min' => '6000000'])->body();
        $this->assertSame(3, $this->cards($rich));
        $this->assertStringContainsString('Senior Accountant', $rich);
        // Sales Exec 1–2M, Nurse 1.2–1.6M, Teacher 0.9–1.3M, Designer 0.8–1.2M, Support 0.9–1.1M overlap 1–1.25M.
        $this->assertSame(5, $this->cards($this->get('/', ['salary_min' => '1000000', 'salary_max' => '1250000'])->body()));
        $this->assertStringNotContainsString('Digital Marketing Intern', $this->get('/', ['salary_max' => '99999999', 'remote' => '1'])->body(), 'undisclosed salary excluded once a salary filter is set');
    }

    public function testSortBySalaryAndFiltersKeptInPagerLinks(): void
    {
        $this->assertSame('Operations Manager', $this->firstTitle('/?sort=salary'));
        $this->assertSame('Frontend Developer (React)', $this->firstTitle('/?sort=salary&remote=1'));
        $this->assertSame('Frontend Developer (React)', $this->firstTitle('/?sort=date'));

        $page1 = $this->get('/', ['sort' => 'date'])->body();
        $this->assertStringContainsString('href="/?sort=date&amp;page=2"', $page1);
    }

    public function testJobPageHasMetaTagsAndJsonLd(): void
    {
        $slug = $this->slug('Frontend Developer');
        $body = $this->get("/jobs/{$slug}")->body();

        $this->assertStringContainsString('<title>Frontend Developer (React) at Savanna Tech Labs, Kampala · KaziUG</title>', $body);
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/jobs/' . $slug . '">', $body);
        $this->assertStringContainsString('<meta property="og:title"', $body);
        preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $body, $m);
        $data = json_decode($m[1] ?? '', true);
        $this->assertSame('JobPosting', $data['@type']);
        $this->assertSame('FULL_TIME', $data['employmentType']);
        $this->assertSame('TELECOMMUTE', $data['jobLocationType']);
        $this->assertSame(['@type' => 'QuantitativeValue', 'minValue' => 3500000, 'maxValue' => 5000000, 'unitText' => 'MONTH'], $data['baseSalary']['value']);
        $this->assertSame('Savanna Tech Labs', $data['hiringOrganization']['name']);
    }

    public function testCategoryPagesAndInactiveCategories(): void
    {
        $body = $this->get('/categories/sales-marketing')->body();
        $this->assertStringContainsString('<h1>Sales &amp; Marketing jobs</h1>', $body);
        $this->assertSame(2, $this->cards($body));

        $this->db->execute("UPDATE job_categories SET is_active = 0 WHERE slug = 'sales-marketing'");
        $this->assertStatus(404, $this->get('/categories/sales-marketing'));
        $this->assertStringNotContainsString('value="sales-marketing"', $this->get('/')->body());
    }

    public function testSavedJobsPageRendersOnlyRequestedVisibleJobs(): void
    {
        $a = $this->jobId('Frontend Developer');
        $b = $this->jobId('Agronomist');
        $body = $this->get('/saved', ['ids' => "{$b},{$a},999999,abc"])->body();

        $this->assertSame(2, $this->cards($body));
        $this->assertLessThan(strpos($body, 'Frontend Developer'), strpos($body, 'Agronomist'), 'keeps saved order');
        $this->assertStringContainsString('haven’t saved any jobs', $this->get('/saved')->body());
    }

    public function testDetailPaneShowsRequestedJob(): void
    {
        $body = $this->get('/', ['job' => $this->slug('Agronomist')])->body();

        $this->assertMatchesRegularExpression('#<h2 class="detail-title"><a href="/jobs/[^"]+">Agronomist</a>#', $body);
    }

    private function firstTitle(string $url): string
    {
        preg_match('/<h3 class="title"><a href="[^"]+">([^<]+)</', $this->get($url)->body(), $m);

        return html_entity_decode($m[1] ?? '');
    }
}
