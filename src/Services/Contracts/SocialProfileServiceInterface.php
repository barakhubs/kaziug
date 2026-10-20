<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\Actor;
use App\Domain\SocialProfile;

interface SocialProfileServiceInterface
{
    /** @return list<SocialProfile> */
    public function all(): array;

    /** @return list<SocialProfile> */
    public function visible(): array;

    public function get(int $id): SocialProfile;

    /** @param array{platform: string, label: string, url: string, icon_key: string, is_visible: bool} $data */
    public function create(array $data, Actor $actor): SocialProfile;

    /** @param array{platform: string, label: string, url: string, icon_key: string, is_visible: bool} $data */
    public function update(int $id, array $data, Actor $actor): void;

    public function setVisible(int $id, bool $visible, Actor $actor): void;

    /** @param 'up'|'down' $direction */
    public function move(int $id, string $direction, Actor $actor): void;

    public function delete(int $id, Actor $actor): void;
}
