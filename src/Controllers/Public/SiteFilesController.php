<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Services\Seo\SiteFiles;

/** /robots.txt, /sitemap.xml, /llms.txt and /jobs.rss, generated from live data. */
final class SiteFilesController
{
    public function __construct(private readonly SiteFiles $files)
    {
    }

    public function robots(Request $request): Response
    {
        return $this->text($this->files->robots(), 'text/plain; charset=UTF-8');
    }

    public function sitemap(Request $request): Response
    {
        return $this->text($this->files->sitemap(), 'application/xml; charset=UTF-8');
    }

    public function llms(Request $request): Response
    {
        return $this->text($this->files->llms(), 'text/plain; charset=UTF-8');
    }

    public function rss(Request $request): Response
    {
        return $this->text($this->files->rss(), 'application/rss+xml; charset=UTF-8');
    }

    private function text(string $body, string $type): Response
    {
        return new Response($body, 200, [
            'Content-Type' => $type,
            'Cache-Control' => 'public, max-age=900',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
