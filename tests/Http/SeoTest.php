<?php

declare(strict_types=1);

namespace Tests\Http;

use App\Console\Commands\SeedCommand;
use App\Console\Io;
use App\Core\Format;
use Tests\Support\HttpTestCase;

/** Search engine (SEO) and AI answer engine (GEO) output. */
final class SeoTest extends HttpTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->container->get(SeedCommand::class)->run([], new Io([], echo: false));
        $this->db->execute("UPDATE settings SET value = '\"256772123456\"' WHERE key = 'whatsapp_number'");
        $this->db->execute("UPDATE settings SET value = '\"hello@kazi.ug\"' WHERE key = 'contact_email'");
        $this->db->execute("UPDATE settings SET value = ? WHERE key = 'physical_address'", [json_encode("Plot 1, Kampala Road\nKampala")]);
    }

    /** @return list<array<string, mixed>> every JSON-LD block on a page */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.+?)</script>#s', $html, $m);

        return array_map(static fn (string $json): array => json_decode($json, true, 512, JSON_THROW_ON_ERROR), $m[1]);
    }

    /** @return array<string, mixed> */
    private function block(string $html, string $type): array
    {
        foreach ($this->jsonLd($html) as $block) {
            if (($block['@type'] ?? null) === $type) {
                return $block;
            }
        }
        $this->fail("No {$type} JSON-LD found");
    }

    public function testRobotsTxtAllowsSearchAndAiCrawlersButNotPrivatePages(): void
    {
        $response = $this->get('/robots.txt');
        $body = $response->body();

        $this->assertStringStartsWith('text/plain', (string) $response->header('Content-Type'));
        $this->assertStringContainsString("User-agent: *\nAllow: /\nDisallow: /admin", $body);
        foreach (['GPTBot', 'PerplexityBot', 'ClaudeBot', 'Google-Extended', 'OAI-SearchBot'] as $bot) {
            $this->assertStringContainsString("User-agent: {$bot}\n", $body);
        }
        $this->assertSame(2, substr_count($body, 'Disallow: /admin'), 'AI group repeats the private paths');
        $this->assertStringContainsString('Sitemap: http://localhost/sitemap.xml', $body);
    }

    public function testSitemapListsEveryVisibleJobAndLandingPage(): void
    {
        $this->db->execute("UPDATE jobs SET status = 'draft', published_at = NULL WHERE title LIKE 'Agronomist%'");

        $response = $this->get('/sitemap.xml');
        $xml = simplexml_load_string($response->body());
        $this->assertNotFalse($xml);
        $locs = array_map('strval', iterator_to_array($xml->url->loc ?? [], false));
        $locs = [];
        foreach ($xml->url as $url) {
            $locs[] = (string) $url->loc;
        }

        $this->assertContains('http://localhost/', $locs);
        $this->assertContains('http://localhost/cv-help', $locs);
        $this->assertContains('http://localhost/categories/technology', $locs);
        $this->assertContains('http://localhost/jobs-in/kampala', $locs);
        $jobUrls = array_filter($locs, static fn (string $l): bool => str_contains($l, '/jobs/'));
        $this->assertCount(13, $jobUrls, 'drafts are left out');
        $this->assertNotContains('http://localhost/jobs/' . $this->db->fetchValue("SELECT slug FROM jobs WHERE title LIKE 'Agronomist%'"), $locs);
        $this->assertStringContainsString('<lastmod>', $response->body());
    }

    public function testLlmsTxtSummarisesSiteForAiAssistants(): void
    {
        $body = $this->get('/llms.txt')->body();

        $this->assertStringStartsWith("# KaziUG\n\n> Jobs in Uganda.", $body);
        $this->assertStringContainsString('[CV help](http://localhost/cv-help): CV fix service for job seekers, UGX 100,000 per CV, also available on WhatsApp', $body);
        $this->assertStringContainsString('[Jobs in Kampala](http://localhost/jobs-in/kampala)', $body);
        $this->assertStringContainsString('## Latest jobs', $body);
        $this->assertStringContainsString('Frontend Developer (React) at Savanna Tech Labs', $body);
        $this->assertStringContainsString('- WhatsApp: +256 772 123456', $body);
    }

    public function testRssFeedIsValidAndLinksJobs(): void
    {
        $response = $this->get('/jobs.rss');
        $xml = simplexml_load_string($response->body());

        $this->assertStringStartsWith('application/rss+xml', (string) $response->header('Content-Type'));
        $this->assertNotFalse($xml);
        $this->assertCount(14, $xml->channel->item);
        $first = $xml->channel->item[0];
        $this->assertSame('Frontend Developer (React) at Savanna Tech Labs (Kampala)', (string) $first->title);
        $this->assertStringStartsWith('http://localhost/jobs/', (string) $first->link);
        $this->assertStringContainsString('<link rel="alternate" type="application/rss+xml"', $this->get('/')->body());
    }

    public function testHomeHasH1OrganizationAndSearchAction(): void
    {
        $html = $this->get('/')->body();

        $this->assertStringContainsString('<h1 class="search-title">Jobs in Uganda</h1>', $html);
        $org = $this->block($html, 'Organization');
        $this->assertSame('KaziUG', $org['name']);
        $this->assertSame('hello@kazi.ug', $org['email']);
        $this->assertSame('Plot 1, Kampala Road, Kampala', $org['address']['streetAddress']);
        $this->assertSame('+256772123456', $org['contactPoint']['telephone']);
        $this->assertContains('https://www.linkedin.com/company/example', $org['sameAs']);
        $website = $this->block($html, 'WebSite');
        $this->assertSame('http://localhost/?q={search_term_string}', $website['potentialAction']['target']['urlTemplate']);
        $this->assertStringContainsString('<meta property="og:title"', $html);
    }

    public function testCityLandingPages(): void
    {
        $page = $this->get('/jobs-in/kampala');
        $html = $page->body();

        $this->assertStatus(200, $page);
        $this->assertStringContainsString('<h1>Jobs in Kampala</h1>', $html);
        $this->assertStringContainsString('<title>Jobs in Kampala, Uganda · KaziUG</title>', $html);
        $this->assertStringContainsString('<link rel="canonical" href="http://localhost/jobs-in/kampala">', $html);
        $this->assertStringNotContainsString('noindex', $html, 'city pages are indexable');
        $count = (int) $this->db->fetchValue("SELECT count(*) FROM jobs WHERE location = 'Kampala'");
        $this->assertStringContainsString("{$count} open jobs in Kampala", $html);
        $this->assertSame(['Jobs', 'Jobs in Kampala'], array_column($this->block($html, 'BreadcrumbList')['itemListElement'], 'name'));
        $this->assertStringContainsString('href="/jobs-in/entebbe"', $html, 'links to other cities');
        $this->assertStringNotContainsString('Mbarara Agronomist', $html);
        $this->assertStringContainsString('Jobs in Kampala</a>', $this->get('/')->body(), 'footer links city pages');

        $this->assertStatus(404, $this->get('/jobs-in/atlantis'));
        $this->assertStringContainsString('noindex', $this->get('/jobs-in/kampala', ['type' => ['contract']])->body());
    }

    public function testJobPageFactsBreadcrumbsAndDescriptionLength(): void
    {
        $slug = (string) $this->db->fetchValue("SELECT slug FROM jobs WHERE title LIKE 'Frontend%'");
        $html = $this->get("/jobs/{$slug}")->body();

        $this->assertStringContainsString('Full-time mid-level Technology role at Savanna Tech Labs in Kampala, Uganda (remote-friendly). Salary: UGX 3.5M – UGX 5M / month.', html_entity_decode($html));
        preg_match('#<meta name="description" content="([^"]*)">#', $html, $m);
        $this->assertLessThanOrEqual(155, mb_strlen(html_entity_decode($m[1])));
        $posting = $this->block($html, 'JobPosting');
        $this->assertFalse($posting['directApply']);
        $this->assertSame('Technology', $posting['industry']);
        $this->assertSame('React, JavaScript, CSS, REST APIs', $posting['skills']);
        $this->assertSame(['Jobs', 'Technology jobs', 'Frontend Developer (React)'], array_column($this->block($html, 'BreadcrumbList')['itemListElement'], 'name'));
        $this->assertStringContainsString('href="/jobs-in/kampala"', $html);
    }

    public function testCvHelpFaqAndServiceStructuredData(): void
    {
        $html = $this->get('/cv-help')->body();

        $this->assertStringContainsString('<summary>How much does the CV fix service cost?</summary>', $html);
        $faq = $this->block($html, 'FAQPage');
        $this->assertSame('How much does the CV fix service cost?', $faq['mainEntity'][0]['name']);
        $this->assertStringContainsString('UGX 100,000 per CV', $faq['mainEntity'][0]['acceptedAnswer']['text']);
        $service = $this->block($html, 'Service');
        $this->assertSame(['100000', 'UGX'], [$service['offers']['price'], $service['offers']['priceCurrency']]);
    }

    public function testExcerptCutsAtWordBoundary(): void
    {
        $this->assertSame('Short text.', Format::excerpt('Short   text.'));
        $cut = Format::excerpt(str_repeat('Kampala jobs board ', 20), 60);
        $this->assertLessThanOrEqual(60, mb_strlen($cut));
        $this->assertStringEndsWith('…', $cut);
        $this->assertStringNotContainsString('boar…', $cut);
    }
}
