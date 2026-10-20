<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Domain\SocialIcon;
use App\Domain\SocialProfile;
use App\Repositories\Contracts\SocialProfileRepositoryInterface;

final class PgSocialProfileRepository implements SocialProfileRepositoryInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    public function all(): array
    {
        return array_map($this->map(...), $this->db->fetchAll('SELECT * FROM social_profiles ORDER BY sort_order, id'));
    }

    public function visible(): array
    {
        return array_map($this->map(...), $this->db->fetchAll('SELECT * FROM social_profiles WHERE is_visible ORDER BY sort_order, id'));
    }

    public function findById(int $id): ?SocialProfile
    {
        $row = $this->db->fetchOne('SELECT * FROM social_profiles WHERE id = ?', [$id]);

        return $row === null ? null : $this->map($row);
    }

    public function existsForPlatform(string $platform): bool
    {
        return (bool) $this->db->fetchValue('SELECT EXISTS (SELECT 1 FROM social_profiles WHERE lower(platform) = lower(?))', [$platform]);
    }

    public function create(array $data): SocialProfile
    {
        $row = $this->db->fetchOne(
            'INSERT INTO social_profiles (platform, label, url, icon_key, is_visible, sort_order)
             VALUES (?, ?, ?, ?, ?, (SELECT coalesce(max(sort_order), 0) + 10 FROM social_profiles))
             RETURNING *',
            [$data['platform'], $data['label'], $data['url'], $data['icon_key'], $data['is_visible']],
        );

        return $this->map($row ?? []);
    }

    public function update(int $id, array $data): void
    {
        $this->db->execute(
            'UPDATE social_profiles SET platform = ?, label = ?, url = ?, icon_key = ?, is_visible = ? WHERE id = ?',
            [$data['platform'], $data['label'], $data['url'], $data['icon_key'], $data['is_visible'], $id],
        );
    }

    public function setVisible(int $id, bool $visible): void
    {
        $this->db->execute('UPDATE social_profiles SET is_visible = ? WHERE id = ?', [$visible, $id]);
    }

    public function reorder(array $orderedIds): void
    {
        $this->db->transaction(function (Database $db) use ($orderedIds): void {
            foreach (array_values($orderedIds) as $index => $id) {
                $db->execute('UPDATE social_profiles SET sort_order = ? WHERE id = ?', [($index + 1) * 10, $id]);
            }
        });
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM social_profiles WHERE id = ?', [$id]);
    }

    /** @param array<string, mixed> $row */
    private function map(array $row): SocialProfile
    {
        return new SocialProfile(
            (int) $row['id'],
            (string) $row['platform'],
            (string) $row['label'],
            (string) $row['url'],
            SocialIcon::from((string) $row['icon_key']),
            (int) $row['sort_order'],
            (bool) $row['is_visible'],
        );
    }
}
