<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Config;
use App\Core\Format;
use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Services\Contracts\SettingsReaderInterface;
use App\Services\Seo\StructuredData;

/**
 * Privacy Policy, Terms of Use and Cookie Policy. The wording lives in
 * config/content/legal/{page}.php; placeholders are filled from Settings so contact details
 * and prices in the policies always match the rest of the site.
 */
final class LegalPageController
{
    /** slug => content file, in the order the pages link to each other */
    public const PAGES = [
        'privacy' => 'Privacy Policy',
        'terms' => 'Terms of Use',
        'cookies' => 'Cookie Policy',
    ];

    public function __construct(
        private readonly SettingsReaderInterface $settings,
        private readonly StructuredData $structuredData,
        private readonly Responder $responder,
        private readonly Config $config,
    ) {
    }

    public function privacy(Request $request): Response
    {
        return $this->page('privacy');
    }

    public function terms(Request $request): Response
    {
        return $this->page('terms');
    }

    public function cookies(Request $request): Response
    {
        return $this->page('cookies');
    }

    private function page(string $slug): Response
    {
        /** @var array{title: string, updated: string, summary: string, sections: list<array<string, mixed>>} $content */
        $content = require dirname(__DIR__, 3) . "/config/content/legal/{$slug}.php";
        $replace = $this->placeholders();
        $fill = static fn (string $text): string => strtr($text, $replace);

        $sections = [];
        foreach ($content['sections'] as $i => $section) {
            $sections[] = [
                'id' => 's' . ($i + 1),
                'heading' => $fill((string) $section['heading']),
                'paragraphs' => array_map($fill, $section['paragraphs'] ?? []),
                'items' => array_map($fill, $section['items'] ?? []),
                'after' => array_map($fill, $section['after'] ?? []),
                'table' => isset($section['table']) ? [
                    'head' => array_map($fill, $section['table']['head']),
                    'rows' => array_map(static fn (array $row): array => array_map($fill, $row), $section['table']['rows']),
                ] : null,
            ];
        }

        return $this->responder->view('public/legal', [
            'slug' => $slug,
            'title' => $content['title'],
            'updated' => new \DateTimeImmutable($content['updated']),
            'summary' => $fill($content['summary']),
            'sections' => $sections,
            'pages' => self::PAGES,
            'jsonLd' => [$this->structuredData->breadcrumbs([['Jobs', '/'], [$content['title'], '/' . $slug]])],
        ]);
    }

    /** @return array<string, string> */
    private function placeholders(): array
    {
        $site = $this->settings->site();
        $ways = [];
        if ($site->contactEmail !== '') {
            $ways[] = 'by email at ' . $site->contactEmail;
        }
        if ($site->hasWhatsapp()) {
            $ways[] = 'on WhatsApp at ' . $site->whatsappDisplay();
        }
        $ways[] = 'through the contact form at ' . $this->config->string('app.url') . '/contact';
        $contact = count($ways) === 1 ? $ways[0] : implode(', ', array_slice($ways, 0, -1)) . ' or ' . end($ways);

        return [
            '{site}' => $site->siteName,
            '{url}' => $this->config->string('app.url'),
            '{contact}' => $contact,
            '{address}' => $site->physicalAddress !== '' ? preg_replace('/\s*\R\s*/', ', ', $site->physicalAddress) : 'the address shown in the footer of our website',
            '{price}' => Format::money($site->cvServicePrice),
        ];
    }
}
