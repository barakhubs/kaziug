<?php

declare(strict_types=1);

namespace App\Domain;

final class SiteSettings
{
    public const DEFAULTS = [
        'site_name' => 'KaziUG',
        'tagline' => 'Jobs in Uganda',
        'contact_email' => '',
        'physical_address' => '',
        'footer_text' => '',
        'jobs_per_page' => 8,
        'meta_description' => 'Find jobs in Uganda.',
        'maintenance_mode' => false,
        'whatsapp_number' => '',
        'cv_service_price' => 100000,
        'whatsapp_channel_url' => '',
    ];

    public function __construct(
        public readonly string $siteName,
        public readonly string $tagline,
        public readonly string $contactEmail,
        public readonly string $physicalAddress,
        public readonly string $footerText,
        public readonly int $jobsPerPage,
        public readonly string $metaDescription,
        public readonly bool $maintenanceMode,
        public readonly ?string $logoVersion,
        public readonly string $whatsappNumber = '',
        public readonly int $cvServicePrice = 100000,
        public readonly string $whatsappChannelUrl = '',
    ) {
    }

    /** @param array<string, mixed> $values stored settings; missing keys fall back to DEFAULTS */
    public static function fromArray(array $values, ?string $logoVersion): self
    {
        $v = [...self::DEFAULTS, ...array_intersect_key($values, self::DEFAULTS)];

        return new self(
            (string) $v['site_name'],
            (string) $v['tagline'],
            (string) $v['contact_email'],
            (string) $v['physical_address'],
            (string) $v['footer_text'],
            max(1, min(100, (int) $v['jobs_per_page'])),
            (string) $v['meta_description'],
            (bool) $v['maintenance_mode'],
            $logoVersion,
            preg_replace('/\D/', '', (string) $v['whatsapp_number']) ?? '',
            max(0, (int) $v['cv_service_price']),
            (string) $v['whatsapp_channel_url'],
        );
    }

    /** The site's own logo and favicon files, used unless a logo is uploaded in Settings. */
    public const DEFAULT_LOGO = '/assets/img/logo.png';
    public const FAVICON = '/assets/img/favicon-32.png';
    public const FAVICON_LARGE = '/assets/img/favicon.png';
    public const APPLE_TOUCH_ICON = '/assets/img/apple-touch-icon.png';

    /** Whether a logo was uploaded in Settings (it then replaces the default logo file). */
    public function hasLogo(): bool
    {
        return $this->logoVersion !== null;
    }

    /** Site-relative path of the logo to show: the uploaded one if any, otherwise the default file. */
    public function logoPath(): string
    {
        return $this->hasLogo() ? '/media/logo?v=' . $this->logoVersion : self::DEFAULT_LOGO;
    }

    public function hasWhatsapp(): bool
    {
        return $this->whatsappNumber !== '';
    }

    /** wa.me click-to-chat link, optionally with a pre-filled message; null when no number is set. */
    public function whatsappLink(string $message = ''): ?string
    {
        if (!$this->hasWhatsapp()) {
            return null;
        }

        return 'https://wa.me/' . $this->whatsappNumber . ($message !== '' ? '?text=' . rawurlencode($message) : '');
    }

    /** Whether to show the floating "Join our WhatsApp channel" button. */
    public function hasWhatsappChannel(): bool
    {
        return $this->whatsappChannelUrl !== '';
    }

    /** "+256 772 123456" style display of the stored digits. */
    public function whatsappDisplay(): string
    {
        $d = $this->whatsappNumber;
        if ($d === '') {
            return '';
        }
        if (str_starts_with($d, '256') && strlen($d) === 12) {
            return '+256 ' . substr($d, 3, 3) . ' ' . substr($d, 6);
        }

        return '+' . $d;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'site_name' => $this->siteName,
            'tagline' => $this->tagline,
            'contact_email' => $this->contactEmail,
            'physical_address' => $this->physicalAddress,
            'footer_text' => $this->footerText,
            'jobs_per_page' => $this->jobsPerPage,
            'meta_description' => $this->metaDescription,
            'maintenance_mode' => $this->maintenanceMode,
            'whatsapp_number' => $this->whatsappNumber,
            'cv_service_price' => $this->cvServicePrice,
            'whatsapp_channel_url' => $this->whatsappChannelUrl,
        ];
    }
}
