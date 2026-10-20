<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\SocialProfile;

interface SocialProfileRepositoryInterface
{
    /** @return list<SocialProfile> in display order */
    public function all(): array;

    /** @return list<SocialProfile> visible ones, in display order */
    public function visible(): array;

    public function findById(int $id): ?SocialProfile;

    public function existsForPlatform(string $platform): bool;

    /** @param array{platform: string, label: string, url: string, icon_key: string, is_visible: bool} $data */
    public function create(array $data): SocialProfile;

    /** @param array{platform: string, label: string, url: string, icon_key: string, is_visible: bool} $data */
    public function update(int $id, array $data): void;

    public function setVisible(int $id, bool $visible): void;

    /** @param list<int> $orderedIds */
    public function reorder(array $orderedIds): void;

    public function delete(int $id): void;
}
