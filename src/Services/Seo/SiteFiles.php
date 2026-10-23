<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Core\Config;
use App\Core\Format;
use App\Domain\Category;
use App\Domain\Job;
use App\Services\Contracts\CategoryServiceInterface;
use App\Services\Contracts\JobSearchServiceInterface;
use App\Services\Contracts\SettingsReaderInterface;

/**
 * The machine-readable files crawlers and AI assistants look for: robots.txt, sitemap.xml,
 * llms.txt and an RSS feed of open jobs. All are generated from live data.
 */
final class SiteFiles
{
    /** AI search and assistant crawlers, explicitly welcomed (a named group overrides "*", so rules repeat). */
    public const AI_CRAWLERS = [
        'GPTBot', 'OAI-SearchBot', 'ChatGPT-User', 'PerplexityBot', 'Perplexity-User', 'ClaudeBot',
        'Claude-SearchBot', 'Claude-User', 'Google-Extended', 'Applebot-Extended', 'CCBot', 'Bingbot',
    ];
    private const PRIVATE_PATHS = ['/admin', '/saved', '/subscribe', '/unsubscribe'];
    private const FEED_SIZE = 50;

    public function __construct(
        private readonly Config $config,
        private readonly SettingsReaderInterface $settings,
        private readonly JobSearchServiceInterface $jobs,
        private readonly CategoryServiceInterface $categories,
    ) {
    }

    public function robots(): string
    {
        $rules = "Allow: /\n" . implode('', array_map(static fn (string $p): string => "Disallow: {$p}\n", self::PRIVATE_PATHS));
        $site = $this->settings->site()->siteName;

        return "# {$site}: job listings may be crawled, indexed and cited.\n"
            . "User-agent: *\n{$rules}\n"
            . "# AI search engines and assistants are welcome to read and cite job listings.\n"
            . implode('', array_map(static fn (string $bot): string => "User-agent: {$bot}\n", self::AI_CRAWLERS))
            . "{$rules}\n"
            . 'Sitemap: ' . $this->url('/sitemap.xml') . "\n";
    }

    public function sitemap(): string
    {
        $urls = [
            [$this->url('/'), null, 'hourly', '1.0'],
            [$this->url('/cv-help'), null, 'monthly', '0.6'],
            [$this->url('/about'), null, 'yearly', '0.4'],
            [$this->url('/privacy'), null, 'yearly', '0.2'],
            [$this->url('/terms'), null, 'yearly', '0.2'],
            [$this->url('/cookies'), null, 'yearly', '0.2'],
            [$this->url('/contact'), null, 'yearly', '0.3'],
        ];
        foreach ($this->activeCategories() as $category) {
            $urls[] = [$this->url('/categories/' . $category->slug), null, 'daily', '0.7'];
        }
        foreach ($this->jobs->cities() as $city) {
            $urls[] = [$this->url('/jobs-in/' . $city['slug']), null, 'daily', '0.7'];
        }
        foreach ($this->jobs->sitemapEntries() as $entry) {
            $urls[] = [$this->url('/jobs/' . $entry['slug']), (new \DateTimeImmutable($entry['updated_at']))->format(DATE_ATOM), 'weekly', '0.8'];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as [$loc, $lastmod, $changefreq, $priority]) {
            $xml .= '  <url><loc>' . $this->xml($loc) . '</loc>'
                . ($lastmod !== null ? '<lastmod>' . $lastmod . '</lastmod>' : '')
                . "<changefreq>{$changefreq}</changefreq><priority>{$priority}</priority></url>\n";
        }

        return $xml . "</urlset>\n";
    }

    /** llms.txt (https://llmstxt.org): a plain summary of the site for AI assistants. */
    public function llms(): string
    {
        $site = $this->settings->site();
        $price = Format::money($site->cvServicePrice);
        $out = "# {$site->siteName}\n\n> {$site->tagline}. {$site->metaDescription}\n\n"
            . "{$site->siteName} lists current job vacancies in Uganda: full-time, part-time, contract and internship roles, "
            . "with salaries in Ugandan shillings (UGX) where employers disclose them. Every job page has the employer, location, "
            . "salary, requirements and how to apply. Job pages disappear when a vacancy closes or expires.\n\n"
            . "## Key pages\n\n"
            . '- [Latest jobs](' . $this->url('/') . "): searchable list of all open vacancies\n"
            . '- [CV help](' . $this->url('/cv-help') . "): CV fix service for job seekers, {$price} per CV"
            . ($site->hasWhatsapp() ? ', also available on WhatsApp' : '') . "\n"
            . '- [About us](' . $this->url('/about') . "): who runs the site and what it offers\n"
            . '- [Contact](' . $this->url('/contact') . ")\n"
            . '- [Jobs feed (RSS)](' . $this->url('/jobs.rss') . "): the newest " . self::FEED_SIZE . " open jobs\n"
            . '- [Sitemap](' . $this->url('/sitemap.xml') . ")\n";

        $cities = $this->jobs->cities();
        if ($cities !== []) {
            $out .= "\n## Jobs by city\n\n";
            foreach ($cities as $city) {
                $out .= "- [Jobs in {$city['name']}](" . $this->url('/jobs-in/' . $city['slug']) . "): {$city['count']} open\n";
            }
        }
        $categories = $this->activeCategories();
        if ($categories !== []) {
            $out .= "\n## Jobs by category\n\n";
            foreach ($categories as $category) {
                $out .= "- [{$category->name}](" . $this->url('/categories/' . $category->slug) . "): {$category->jobCount} open\n";
            }
        }

        $latest = $this->jobs->latest(20);
        if ($latest !== []) {
            $out .= "\n## Latest jobs\n\n";
            foreach ($latest as $job) {
                $out .= "- [{$job->title} at {$job->company}](" . $this->url('/jobs/' . $job->slug) . '): '
                    . Format::jobFacts($job) . ' Posted ' . Format::date($job->publishedAt) . ".\n";
            }
        }
        if ($site->contactEmail !== '' || $site->hasWhatsapp()) {
            $out .= "\n## Contact\n\n";
            $out .= $site->contactEmail !== '' ? "- Email: {$site->contactEmail}\n" : '';
            $out .= $site->hasWhatsapp() ? '- WhatsApp: ' . $site->whatsappDisplay() . "\n" : '';
        }

        return $out;
    }

    public function rss(): string
    {
        $site = $this->settings->site();
        $jobs = $this->jobs->latest(self::FEED_SIZE);
        $built = ($jobs[0]->publishedAt ?? new \DateTimeImmutable())->format(DATE_RSS);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>' . "\n"
            . '<title>' . $this->xml("{$site->siteName}: {$site->tagline}") . '</title>'
            . '<link>' . $this->xml($this->url('/')) . '</link>'
            . '<description>' . $this->xml($site->metaDescription) . '</description>'
            . '<language>en-ug</language><lastBuildDate>' . $built . '</lastBuildDate>'
            . '<atom:link href="' . $this->xml($this->url('/jobs.rss')) . '" rel="self" type="application/rss+xml"/>' . "\n";
        foreach ($jobs as $job) {
            $xml .= $this->rssItem($job);
        }

        return $xml . "</channel></rss>\n";
    }

    private function rssItem(Job $job): string
    {
        $url = $this->url('/jobs/' . $job->slug);

        return '<item><title>' . $this->xml("{$job->title} at {$job->company} ({$job->location})") . '</title>'
            . '<link>' . $this->xml($url) . '</link><guid isPermaLink="true">' . $this->xml($url) . '</guid>'
            . '<pubDate>' . ($job->publishedAt ?? $job->createdAt)->format(DATE_RSS) . '</pubDate>'
            . '<category>' . $this->xml($job->categoryName) . '</category>'
            . '<description>' . $this->xml(Format::jobFacts($job) . ' ' . $job->summary) . "</description></item>\n";
    }

    /** @return list<Category> active categories that currently have open jobs */
    private function activeCategories(): array
    {
        return array_values(array_filter($this->categories->active(), static fn (Category $c): bool => $c->jobCount > 0));
    }

    private function url(string $path): string
    {
        return $this->config->string('app.url') . $path;
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
