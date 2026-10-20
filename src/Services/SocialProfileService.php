<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Actor;
use App\Domain\NotFoundException;
use App\Domain\SocialProfile;
use App\Repositories\Contracts\SocialProfileRepositoryInterface;
use App\Services\Contracts\AuditServiceInterface;
use App\Services\Contracts\SocialProfileServiceInterface;

final class SocialProfileService implements SocialProfileServiceInterface
{
    public function __construct(
        private readonly SocialProfileRepositoryInterface $profiles,
        private readonly AuditServiceInterface $audit,
    ) {
    }

    public function all(): array
    {
        return $this->profiles->all();
    }

    public function visible(): array
    {
        return $this->profiles->visible();
    }

    public function get(int $id): SocialProfile
    {
        return $this->profiles->findById($id) ?? throw new NotFoundException('Social profile not found.');
    }

    public function create(array $data, Actor $actor): SocialProfile
    {
        $profile = $this->profiles->create($data);
        $this->audit->record($actor, 'social_profile.created', 'social_profile', $profile->id, ['platform' => $data['platform'], 'url' => $data['url']]);

        return $profile;
    }

    public function update(int $id, array $data, Actor $actor): void
    {
        $this->get($id);
        $this->profiles->update($id, $data);
        $this->audit->record($actor, 'social_profile.updated', 'social_profile', $id, ['platform' => $data['platform'], 'url' => $data['url']]);
    }

    public function setVisible(int $id, bool $visible, Actor $actor): void
    {
        $this->get($id);
        $this->profiles->setVisible($id, $visible);
        $this->audit->record($actor, $visible ? 'social_profile.shown' : 'social_profile.hidden', 'social_profile', $id);
    }

    public function move(int $id, string $direction, Actor $actor): void
    {
        $ids = array_map(static fn (SocialProfile $p): int => $p->id, $this->profiles->all());
        $index = array_search($id, $ids, true);
        if ($index === false) {
            throw new NotFoundException('Social profile not found.');
        }
        $target = $direction === 'up' ? $index - 1 : $index + 1;
        if ($target < 0 || $target >= count($ids)) {
            return;
        }
        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
        $this->profiles->reorder($ids);
        $this->audit->record($actor, 'social_profile.reordered', 'social_profile', $id, ['direction' => $direction]);
    }

    public function delete(int $id, Actor $actor): void
    {
        $profile = $this->get($id);
        $this->profiles->delete($id);
        $this->audit->record($actor, 'social_profile.deleted', 'social_profile', $id, ['platform' => $profile->platform]);
    }
}
