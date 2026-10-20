<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Log\LoggerInterface;
use App\Domain\SiteSettings;
use App\Repositories\Contracts\SettingsRepositoryInterface;
use App\Services\Contracts\SettingsReaderInterface;
use Throwable;

/**
 * Loads settings once per request. Falls back to defaults if the database is unavailable,
 * so error pages and /health can still render.
 */
final class SettingsReader implements SettingsReaderInterface
{
    private ?SiteSettings $cached = null;

    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function site(): SiteSettings
    {
        if ($this->cached !== null) {
            return $this->cached;
        }

        try {
            return $this->cached = SiteSettings::fromArray($this->settings->all(), $this->settings->assetVersion('logo'));
        } catch (Throwable $e) {
            $this->logger->error('Could not load site settings; using defaults', ['error' => $e->getMessage()]);

            return SiteSettings::fromArray([], null);
        }
    }

    /** Called after settings change so the rest of the request sees the new values. */
    public function forget(): void
    {
        $this->cached = null;
    }
}
