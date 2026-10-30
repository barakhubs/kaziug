<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Console\Commands\SeedCommand;
use App\Console\Io;
use App\Core\Database\Database;
use App\Repositories\Contracts\AnalyticsRepositoryInterface;
use App\Services\AnalyticsRecorder;
use App\Services\Contracts\AnalyticsRollupInterface;
use App\Services\DeviceClassifier;
use App\Services\TrafficFilter;
use App\Services\VisitorHasher;
use DateTimeImmutable;
use RuntimeException;
use Tests\Support\AdminHttpTestCase;
use Tests\Support\Fakes\NullLogger;

final class AnalyticsTest extends AdminHttpTestCase
{
    private const BROWSER = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36';

    private int $jobId;

    private string $slug;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container->get(SeedCommand::class)->run([], new Io([], echo: false));
        $job = $this->db->fetchOne("SELECT id, slug FROM jobs WHERE status = 'published' ORDER BY id LIMIT 1");
        $this->jobId = (int) $job['id'];
        $this->slug = (string) $job['slug'];
        $this->db->execute(
            "UPDATE jobs SET apply_url = 'https://employer.example/careers/42', apply_email = 'hr@employer.example' WHERE id = ?",
            [$this->jobId],
        );
        $this->actAsVisitor();
    }

    public function testJobPageViewIsRecordedAnonymously(): void
    {
        $this->headers['referer'] = 'https://www.Google.com/search?q=jobs+in+kampala';
        $this->assertStatus(200, $this->get("/jobs/{$this->slug}", ['utm_source' => 'WhatsApp', 'utm_campaign' => 'Oct Alerts']));

        $event = $this->db->fetchOne('SELECT * FROM analytics_events');
        $this->assertSame('pageview', $event['type']);
        $this->assertSame('job', $event['page_type']);
        $this->assertSame($this->jobId, (int) $event['job_id']);
        $this->assertNotNull($event['category_id']);
        $this->assertSame("/jobs/{$this->slug}", $event['path']);
        $this->assertSame('google.com', $event['referrer_host']);
        $this->assertSame('whatsapp', $event['utm_source']);
        $this->assertSame('oct alerts', $event['utm_campaign']);
        $this->assertSame('mobile', $event['device']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $event['visitor_hash']);

        // Nothing identifying is stored anywhere in the row.
        $row = json_encode($event);
        $this->assertStringNotContainsString('127.0.0.1', $row);
        $this->assertStringNotContainsString('Android', $row);
        $this->assertStringNotContainsString('jobs+in+kampala', $row);
    }

    public function testSameVisitorKeepsOneHashWithinADay(): void
    {
        $this->get('/');
        $this->get("/jobs/{$this->slug}");
        $this->headers['user-agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0';
        $this->get('/');

        $this->assertSame(2, (int) $this->db->fetchValue('SELECT count(DISTINCT visitor_hash) FROM analytics_events'));
        $this->assertSame(1, (int) $this->db->fetchValue('SELECT count(*) FROM analytics_salts'));
    }

    public function testInternalReferrerIsNotStored(): void
    {
        $this->headers['referer'] = 'http://localhost:8000/categories/technology';
        $this->get("/jobs/{$this->slug}");

        $this->assertNull($this->db->fetchValue('SELECT referrer_host FROM analytics_events'));
    }

    public function testBotsOptOutsAdminAndErrorsAreNotCounted(): void
    {
        $this->headers['user-agent'] = 'Mozilla/5.0 (compatible; Googlebot/2.1)';
        $this->get('/');
        $this->headers['user-agent'] = self::BROWSER;
        $this->headers['dnt'] = '1';
        $this->get('/');
        unset($this->headers['dnt']);
        $this->headers['sec-gpc'] = '1';
        $this->get('/');
        unset($this->headers['sec-gpc']);
        $this->assertStatus(404, $this->get('/jobs/no-such-job'));
        $this->get('/subscribe/confirm/' . str_repeat('k', 43));
        $this->get('/unsubscribe/' . str_repeat('k', 43));

        $this->signIn();
        $this->get('/');
        $this->get("/jobs/{$this->slug}");

        $this->assertSame(0, (int) $this->db->fetchValue('SELECT count(*) FROM analytics_events'));
    }

    public function testSearchIsRecordedOnItsFirstPageOnly(): void
    {
        $this->get('/', ['q' => '  React ', 'type' => ['full-time'], 'remote' => '1']);
        $this->get('/', ['q' => 'React', 'page' => '2']);
        $this->get('/', ['q' => 'zzzz-no-such-thing']);

        $rows = $this->db->fetchAll('SELECT search_query, search_filters AS filters, result_count FROM analytics_events ORDER BY id');
        $this->assertSame('react', $rows[0]['search_query']);
        $this->assertSame(['type', 'remote'], json_decode((string) $rows[0]['filters'], true));
        $this->assertGreaterThan(0, $rows[0]['result_count']);
        $this->assertNull($rows[1]['result_count'], 'page 2 is not a new search');
        $this->assertSame(0, $rows[2]['result_count']);
    }

    public function testCategoryPageAndPickedJobInTheList(): void
    {
        $this->get('/categories/technology');
        $this->get('/', ['job' => $this->slug]);
        $this->get('/');

        $rows = $this->db->fetchAll('SELECT page_type, job_id, category_id FROM analytics_events ORDER BY id');
        $this->assertSame('category', $rows[0]['page_type']);
        $this->assertNotNull($rows[0]['category_id']);
        $this->assertSame($this->jobId, $rows[1]['job_id'], 'a job the visitor opened in the list counts as a job view');
        $this->assertNull($rows[2]['job_id'], 'the default first job in the pane does not');
    }

    public function testApplyLinksRedirectToTheStoredDestinationAndCountTheClick(): void
    {
        $link = $this->get("/jobs/{$this->slug}/apply/link", ['to' => 'https://evil.example']);
        $this->assertSame(302, $link->status());
        $this->assertSame('https://employer.example/careers/42', $link->header('Location'));
        $this->assertSame('noindex, nofollow', $link->header('X-Robots-Tag'));

        $email = $this->get("/jobs/{$this->slug}/apply/email");
        $this->assertStringStartsWith('mailto:hr@employer.example?subject=Application%3A%20', (string) $email->header('Location'));

        $rows = $this->db->fetchAll("SELECT apply_channel, job_id FROM analytics_events WHERE type = 'apply_click' ORDER BY id");
        $this->assertSame([['apply_channel' => 'link', 'job_id' => $this->jobId], ['apply_channel' => 'email', 'job_id' => $this->jobId]], $rows);
        $this->assertStringContainsString("/jobs/{$this->slug}/apply/link", $this->get("/jobs/{$this->slug}")->body());
    }

    public function testApplyRedirectRefusesHiddenJobsAndMissingDestinations(): void
    {
        $this->db->execute('UPDATE jobs SET apply_email = NULL WHERE id = ?', [$this->jobId]);
        $this->assertStatus(404, $this->get("/jobs/{$this->slug}/apply/email"));
        $this->assertStatus(404, $this->get("/jobs/{$this->slug}/apply/whatsapp"));

        $this->db->execute("UPDATE jobs SET status = 'draft' WHERE id = ?", [$this->jobId]);
        $this->assertStatus(404, $this->get("/jobs/{$this->slug}/apply/link"));
        $this->assertSame(0, (int) $this->db->fetchValue('SELECT count(*) FROM analytics_events'));
    }

    public function testRecorderFailureNeverBreaksThePage(): void
    {
        $failing = new class () implements AnalyticsRepositoryInterface {
            public function record(\App\Domain\AnalyticsEvent $event): void
            {
                throw new RuntimeException('database down');
            }

            public function latestRolledDay(): ?DateTimeImmutable
            {
                return null;
            }

            public function earliestEventAt(): ?DateTimeImmutable
            {
                return null;
            }

            public function rollUpDay(DateTimeImmutable $day, DateTimeImmutable $start, DateTimeImmutable $end): void
            {
            }

            public function purgeEventsBefore(DateTimeImmutable $cutoff): int
            {
                return 0;
            }

            public function totals(\App\Domain\DateRange $range, \App\Domain\AnalyticsDimension $dimension = \App\Domain\AnalyticsDimension::Total, ?string $key = ''): array
            {
                return [];
            }

            public function daily(\App\Domain\DateRange $range, \App\Domain\AnalyticsDimension $dimension = \App\Domain\AnalyticsDimension::Total, string $key = ''): array
            {
                return [];
            }

            public function top(\App\Domain\AnalyticsDimension $dimension, \App\Domain\DateRange $range, string $sort = 'pageviews', ?int $limit = 10, bool $onlyZeroResults = false): array
            {
                return [];
            }
        };
        $this->container->instance(AnalyticsRecorder::class, new AnalyticsRecorder(
            $failing,
            $this->container->get(TrafficFilter::class),
            $this->container->get(VisitorHasher::class),
            new DeviceClassifier(),
            $this->container->get(\App\Core\Config::class),
            new NullLogger(),
        ));

        $this->assertStatus(200, $this->get("/jobs/{$this->slug}"));
        $this->assertSame(302, $this->get("/jobs/{$this->slug}/apply/link")->status());
        $this->assertSame(0, (int) $this->db->fetchValue('SELECT count(*) FROM analytics_events'), 'the failing repository was used');
    }

    public function testAnalyticsPageShowsTheNumbers(): void
    {
        $this->get('/', ['q' => 'astronaut']);
        $this->get("/jobs/{$this->slug}");
        $this->get("/jobs/{$this->slug}/apply/link");
        $this->headers['user-agent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/130.0';
        $this->headers['referer'] = 'https://twitter.com/kaziug';
        $this->get("/jobs/{$this->slug}");

        $this->signIn();
        $body = $this->get('/admin/analytics')->body();

        $this->assertStringContainsString('<h1>Analytics</h1>', $body);
        $this->assertStringContainsString('astronaut', $body, 'zero-result search is listed');
        $this->assertStringContainsString('twitter.com', $body);
        $this->assertStringContainsString("/admin/jobs/{$this->jobId}", $body);
        $this->assertStringContainsString('Application link', $body);
        $this->assertStringContainsString('<svg', $body);

        $totals = $this->db->fetchOne("SELECT * FROM analytics_daily WHERE dimension = 'total'");
        $this->assertSame(3, $totals['pageviews']);
        $this->assertSame(2, $totals['visitors']);
        $this->assertSame(1, $totals['applies']);
        $this->assertSame(1, $totals['searches']);
        $this->assertSame(1, $totals['zero_results']);
        $job = $this->db->fetchOne("SELECT * FROM analytics_daily WHERE dimension = 'job' AND `key` = ?", [(string) $this->jobId]);
        $this->assertSame([2, 2, 1], [$job['pageviews'], $job['visitors'], $job['applies']]);

        // The admin's own visits to the site are not counted.
        $this->get('/');
        $this->assertSame(4, (int) $this->db->fetchValue('SELECT count(*) FROM analytics_events'));
    }

    public function testRangeSelectionAndPreviousPeriodComparison(): void
    {
        // Today is rebuilt from (no) raw events when the page loads, so the data sits on earlier days.
        $this->insertDaily((new DateTimeImmutable('today'))->format('Y-m-d'), 0);
        $this->insertDaily((new DateTimeImmutable('-2 days'))->format('Y-m-d'), 50);
        $this->insertDaily((new DateTimeImmutable('-10 days'))->format('Y-m-d'), 40);
        $this->signIn();

        $week = $this->get('/admin/analytics', ['range' => '7'])->body();
        $this->assertMatchesRegularExpression('/Visitors<\/span>\s*<strong>50<\/strong>/', $week);
        $this->assertStringContainsString('+25%', $week);

        $custom = $this->get('/admin/analytics', ['from' => '2001-01-01', 'to' => '2001-01-31'])->body();
        $this->assertMatchesRegularExpression('/Visitors<\/span>\s*<strong>0<\/strong>/', $custom);
        $this->assertStatus(200, $this->get('/admin/analytics', ['range' => 'nope', 'from' => '2026-99-99']));
    }

    public function testCsvExportIsAuditedAndRejectsUnknownTables(): void
    {
        $this->get("/jobs/{$this->slug}");
        $this->get("/jobs/{$this->slug}/apply/link");
        $this->signIn();

        $response = $this->get('/admin/analytics/export.csv', ['table' => 'jobs', 'range' => '7']);
        $this->assertSame(200, $response->status());
        $this->assertStringContainsString('attachment; filename="analytics-jobs-', (string) $response->header('Content-Disposition'));
        $lines = array_values(array_filter(explode("\n", $response->body())));
        $this->assertSame('key,label,page_views,visitors,apply_clicks,searches,zero_result_searches', $lines[0]);
        $this->assertStringStartsWith("{$this->jobId},", $lines[1]);
        $this->assertStringEndsWith(',1,1,1,0,0', $lines[1]);
        $this->assertSame(1, $this->auditCount('analytics.exported'));

        $daily = $this->get('/admin/analytics/export.csv', ['table' => 'daily', 'range' => '7'])->body();
        $this->assertCount(8, array_filter(explode("\n", $daily)), 'header + 7 days');

        $this->assertStatus(404, $this->get('/admin/analytics/export.csv', ['table' => 'subscribers']));
        $this->assertSame(2, $this->auditCount('analytics.exported'));
    }

    public function testDashboardAndJobDetailsShowAnalytics(): void
    {
        $this->get("/jobs/{$this->slug}");
        $this->get("/jobs/{$this->slug}/apply/email");
        $this->signIn();

        $dashboard = $this->get('/admin')->body();
        $this->assertStringContainsString('Last 7 days', $dashboard);
        $this->assertStringContainsString('Full analytics', $dashboard);

        $details = $this->get("/admin/jobs/{$this->jobId}")->body();
        $this->assertStringContainsString('Last 30 days', $details);
        $this->assertMatchesRegularExpression('/Apply rate<\/dt><dd>100%/', $details);
    }

    public function testRollupKeepsDailyTotalsButPurgesRawEventsPastRetention(): void
    {
        $this->get('/');
        $this->db->execute("UPDATE analytics_events SET occurred_at = NOW(6) - INTERVAL 400 DAY");
        $old = (new DateTimeImmutable('-400 days'))->format('Y-m-d');
        $this->insertDaily($old, 7);

        $rollup = $this->container->get(AnalyticsRollupInterface::class);
        $this->assertGreaterThan(0, $rollup->catchUp());

        $this->assertSame(0, (int) $this->db->fetchValue('SELECT count(*) FROM analytics_events'));
        $this->assertSame(7, (int) $this->db->fetchValue("SELECT visitors FROM analytics_daily WHERE day = ? AND dimension = 'total'", [$old]));
    }

    public function testOldSaltsAreDeletedWhenANewDayStarts(): void
    {
        $this->db->execute("INSERT INTO analytics_salts (day, salt) VALUES (CURRENT_DATE - INTERVAL 2 DAY, UNHEX(REPEAT('ab', 32)))");
        $this->get('/');

        $this->assertSame(1, (int) $this->db->fetchValue('SELECT count(*) FROM analytics_salts'));
    }

    private function actAsVisitor(): void
    {
        $this->cookies = [];
        $this->headers = ['user-agent' => self::BROWSER];
    }

    private function signIn(): void
    {
        $this->post('/admin/login', ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD]);
    }

    private function insertDaily(string $day, int $visitors): void
    {
        $this->container->get(Database::class)->execute(
            "INSERT INTO analytics_daily (day, dimension, `key`, pageviews, visitors) VALUES (?, 'total', '', ?, ?)",
            [$day, $visitors * 2, $visitors],
        );
    }
}
