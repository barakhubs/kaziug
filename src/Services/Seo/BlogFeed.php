<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Core\Config;
use App\Domain\BlogPost;
use App\Services\Contracts\BlogReaderInterface;
use App\Services\Contracts\SettingsReaderInterface;
use DateTimeImmutable;

/** /blog.rss: the newest live articles as an RSS 2.0 feed. */
final class BlogFeed
{
    private const SIZE = 30;

    public function __construct(
        private readonly Config $config,
        private readonly SettingsReaderInterface $settings,
        private readonly BlogReaderInterface $blog,
    ) {
    }

    public function rss(): string
    {
        $site = $this->settings->site();
        $posts = $this->blog->latest(self::SIZE);
        $built = ($posts[0]->publishedAt ?? new DateTimeImmutable())->format(DATE_RSS);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom"><channel>' . "\n"
            . '<title>' . $this->xml("{$site->siteName} blog") . '</title>'
            . '<link>' . $this->xml($this->url('/blog')) . '</link>'
            . '<description>' . $this->xml("Career advice and job-seeking tips for Uganda from {$site->siteName}.") . '</description>'
            . '<language>en-ug</language><lastBuildDate>' . $built . '</lastBuildDate>'
            . '<atom:link href="' . $this->xml($this->url('/blog.rss')) . '" rel="self" type="application/rss+xml"/>' . "\n";
        foreach ($posts as $post) {
            $xml .= $this->item($post);
        }

        return $xml . "</channel></rss>\n";
    }

    private function item(BlogPost $post): string
    {
        $url = $this->url('/blog/' . $post->slug);

        return '<item><title>' . $this->xml($post->title) . '</title>'
            . '<link>' . $this->xml($url) . '</link><guid isPermaLink="true">' . $this->xml($url) . '</guid>'
            . '<pubDate>' . ($post->publishedAt ?? $post->createdAt)->format(DATE_RSS) . '</pubDate>'
            . ($post->jobCategoryName !== null ? '<category>' . $this->xml($post->jobCategoryName) . '</category>' : '')
            . '<description>' . $this->xml($post->description()) . "</description></item>\n";
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
