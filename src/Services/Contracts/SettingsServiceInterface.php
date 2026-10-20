<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\Actor;

/** Write side of site settings (back office). */
interface SettingsServiceInterface
{
    /** @param array<string, mixed> $values validated values for SiteSettings::DEFAULTS keys */
    public function update(array $values, Actor $actor): void;

    /**
     * Validates the image by its content (not its name or the browser's claimed type) and stores it.
     * @throws \App\Domain\DomainException
     */
    public function uploadLogo(string $bytes, Actor $actor): void;

    public function removeLogo(Actor $actor): void;

    /** @return array{mime_type: string, content: string, version: string}|null */
    public function logo(): ?array;
}
