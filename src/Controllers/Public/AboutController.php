<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Format;
use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Domain\Category;
use App\Services\Contracts\CategoryServiceInterface;
use App\Services\Contracts\JobSearchServiceInterface;
use App\Services\Contracts\SettingsReaderInterface;
use App\Services\Seo\StructuredData;

/** /about: who the site is, with live figures. Wording lives in config/content/about.php. */
final class AboutController
{
    public function __construct(
        private readonly JobSearchServiceInterface $jobs,
        private readonly CategoryServiceInterface $categories,
        private readonly SettingsReaderInterface $settings,
        private readonly StructuredData $structuredData,
        private readonly Responder $responder,
    ) {
    }

    public function show(Request $request): Response
    {
        $site = $this->settings->site();
        $cities = $this->jobs->cities();
        $replace = ['{site}' => $site->siteName, '{price}' => Format::money($site->cvServicePrice)];
        /** @var array{intro: string, sections: list<array{heading: string, paragraphs: list<string>}>} $content */
        $content = require dirname(__DIR__, 3) . '/config/content/about.php';

        return $this->responder->view('public/about', [
            'intro' => strtr($content['intro'], $replace),
            'sections' => array_map(static fn (array $s): array => [
                'heading' => strtr($s['heading'], $replace),
                'paragraphs' => array_map(static fn (string $p): string => strtr($p, $replace), $s['paragraphs']),
            ], $content['sections']),
            'stats' => [
                'jobs' => array_sum(array_column($cities, 'count')),
                'cities' => count($cities),
                'categories' => count(array_filter($this->categories->active(), static fn (Category $c): bool => $c->jobCount > 0)),
            ],
            'jsonLd' => [
                $this->structuredData->aboutPage(),
                $this->structuredData->breadcrumbs([['Jobs', '/'], ['About us', '/about']]),
            ],
        ]);
    }
}
