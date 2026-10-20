<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Actor;
use App\Domain\SiteSettings;
use App\Repositories\Contracts\SettingsRepositoryInterface;
use App\Services\Contracts\AuditServiceInterface;
use App\Services\Contracts\SettingsReaderInterface;
use App\Services\Contracts\SettingsServiceInterface;

final class SettingsService implements SettingsServiceInterface
{
    private const LOGO = 'logo';

    public function __construct(
        private readonly SettingsRepositoryInterface $settings,
        private readonly SettingsReaderInterface $reader,
        private readonly ImageInspector $images,
        private readonly AuditServiceInterface $audit,
    ) {
    }

    public function update(array $values, Actor $actor): void
    {
        $values = array_intersect_key($values, SiteSettings::DEFAULTS);
        $before = $this->reader->site()->toArray();
        $changed = array_keys(array_filter($values, static fn (mixed $v, string $k): bool => ($before[$k] ?? null) !== $v, ARRAY_FILTER_USE_BOTH));

        $this->settings->setMany($values);
        $this->reader->forget();

        if ($changed !== []) {
            $metadata = ['changed' => $changed];
            if (in_array('maintenance_mode', $changed, true)) {
                $metadata['maintenance_mode'] = $values['maintenance_mode'];
            }
            $this->audit->record($actor, 'settings.updated', 'settings', null, $metadata);
        }
    }

    public function uploadLogo(string $bytes, Actor $actor): void
    {
        $mime = $this->images->assertAcceptableLogo($bytes);
        $this->settings->putAsset(self::LOGO, $mime, $bytes);
        $this->reader->forget();
        $this->audit->record($actor, 'settings.logo_uploaded', 'settings', self::LOGO, ['mime_type' => $mime, 'bytes' => strlen($bytes)]);
    }

    public function removeLogo(Actor $actor): void
    {
        $this->settings->deleteAsset(self::LOGO);
        $this->reader->forget();
        $this->audit->record($actor, 'settings.logo_removed', 'settings', self::LOGO);
    }

    public function logo(): ?array
    {
        return $this->settings->asset(self::LOGO);
    }
}
