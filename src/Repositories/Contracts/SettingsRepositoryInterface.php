<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

/** Key/value site settings plus binary site assets (the logo). */
interface SettingsRepositoryInterface
{
    /** @return array<string, mixed> decoded values by key */
    public function all(): array;

    /** @param array<string, mixed> $values */
    public function setMany(array $values): void;

    /** @return string|null last-change marker of the asset, null when absent */
    public function assetVersion(string $key): ?string;

    /** @return array{mime_type: string, content: string, version: string}|null */
    public function asset(string $key): ?array;

    public function putAsset(string $key, string $mimeType, string $content): void;

    public function deleteAsset(string $key): void;
}
