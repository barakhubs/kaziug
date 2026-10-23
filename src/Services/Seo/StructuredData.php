<?php

declare(strict_types=1);

namespace App\Services\Seo;

use App\Core\Config;
use App\Domain\SiteSettings;
use App\Domain\SocialProfile;
use App\Services\Contracts\SettingsReaderInterface;
use App\Services\Contracts\SocialProfileServiceInterface;

/**
 * schema.org JSON-LD for search engines and AI assistants: who the site is (Organization),
 * how to search it (WebSite), where a page sits (BreadcrumbList), the CV service and its FAQ.
 * Every method returns JSON that is safe to place inside <script type="application/ld+json">.
 */
final class StructuredData
{
    public function __construct(
        private readonly Config $config,
        private readonly SettingsReaderInterface $settings,
        private readonly SocialProfileServiceInterface $profiles,
    ) {
    }

    public function organization(): string
    {
        $site = $this->settings->site();
        $url = $this->url('/');
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            '@id' => $url . '#organization',
            'name' => $site->siteName,
            'url' => $url,
            'description' => $site->metaDescription,
            'areaServed' => ['@type' => 'Country', 'name' => 'Uganda'],
        ];
        $data['logo'] = $this->url($site->logoPath());
        if ($site->contactEmail !== '') {
            $data['email'] = $site->contactEmail;
        }
        if ($site->physicalAddress !== '') {
            $data['address'] = ['@type' => 'PostalAddress', 'streetAddress' => preg_replace('/\s*\R\s*/', ', ', $site->physicalAddress), 'addressCountry' => 'UG'];
        }
        if ($site->hasWhatsapp()) {
            $data['contactPoint'] = [
                '@type' => 'ContactPoint',
                'contactType' => 'customer support',
                'telephone' => '+' . $site->whatsappNumber,
                'url' => $this->url('/contact'),
                'availableLanguage' => 'English',
            ];
        }
        $sameAs = array_map(static fn (SocialProfile $p): string => $p->url, $this->profiles->visible());
        if ($sameAs !== []) {
            $data['sameAs'] = $sameAs;
        }

        return $this->encode($data);
    }

    /** WebSite with a SearchAction, so search engines can offer a search box for the site. */
    public function website(): string
    {
        $site = $this->settings->site();

        return $this->encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            '@id' => $this->url('/') . '#website',
            'name' => $site->siteName,
            'url' => $this->url('/'),
            'inLanguage' => 'en-UG',
            'publisher' => ['@id' => $this->url('/') . '#organization'],
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => ['@type' => 'EntryPoint', 'urlTemplate' => $this->url('/') . '?q={search_term_string}'],
                'query-input' => 'required name=search_term_string',
            ],
        ]);
    }

    /** @param list<array{0: string, 1: string}> $trail [name, path] pairs from the home page down */
    public function breadcrumbs(array $trail): string
    {
        $items = [];
        foreach (array_values($trail) as $i => [$name, $path]) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => $this->url($path)];
        }

        return $this->encode(['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items]);
    }

    public function cvService(SiteSettings $site): string
    {
        return $this->encode([
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => 'CV fix service',
            'serviceType' => 'CV writing and review',
            'description' => 'Your CV reviewed and fixed so it presents your experience clearly to employers in Uganda.',
            'url' => $this->url('/cv-help'),
            'provider' => ['@id' => $this->url('/') . '#organization', 'name' => $site->siteName],
            'areaServed' => ['@type' => 'Country', 'name' => 'Uganda'],
            'offers' => [
                '@type' => 'Offer',
                'price' => (string) $site->cvServicePrice,
                'priceCurrency' => 'UGX',
                'url' => $this->url('/cv-help'),
                'availability' => 'https://schema.org/InStock',
            ],
        ]);
    }

    /** @param list<array{question: string, answer: string}> $faq */
    public function faq(array $faq): string
    {
        return $this->encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $item): array => [
                '@type' => 'Question',
                'name' => $item['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['answer']],
            ], $faq),
        ]);
    }

    public function url(string $path): string
    {
        return $this->config->string('app.url') . $path;
    }

    /** @param array<string, mixed> $data */
    public static function encode(array $data): string
    {
        // JSON_HEX_* keeps "</script>" and quotes inert inside the script element.
        return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
