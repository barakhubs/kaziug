<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Core\Database\DbTime;
use App\Domain\Popup;
use App\Domain\PopupInput;
use App\Domain\PopupTrigger;
use App\Repositories\Contracts\PopupRepositoryInterface;
use DateTimeImmutable;
use PDO;

final class MysqlPopupRepository implements PopupRepositoryInterface
{
    // The image bytes are never selected here; they are only read by image().
    private const SELECT = 'SELECT id, name, heading, body_html, button_label, button_url, trigger_type, delay_seconds,
        starts_at, ends_at, is_active, content_version, image_mime IS NOT NULL AS has_image, image_version, image_alt,
        updated_at FROM popups';

    public function __construct(private readonly Database $db)
    {
    }

    public function all(): array
    {
        return array_map(self::map(...), $this->db->fetchAll(self::SELECT . ' ORDER BY is_active DESC, updated_at DESC, id DESC'));
    }

    public function findById(int $id): ?Popup
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE id = ?', [$id]);

        return $row === null ? null : self::map($row);
    }

    public function findActive(): ?Popup
    {
        $row = $this->db->fetchOne(self::SELECT . ' WHERE is_active = 1');

        return $row === null ? null : self::map($row);
    }

    public function insert(PopupInput $input, string $bodyHtml): int
    {
        return $this->db->insert(
            'INSERT INTO popups (name, heading, body_html, button_label, button_url, trigger_type, delay_seconds,
                starts_at, ends_at, image_alt)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            $this->fieldValues($input, $bodyHtml),
        );
    }

    public function update(int $id, PopupInput $input, string $bodyHtml, bool $bumpVersion): void
    {
        $this->db->execute(
            'UPDATE popups SET name = ?, heading = ?, body_html = ?, button_label = ?, button_url = ?, trigger_type = ?,
                delay_seconds = ?, starts_at = ?, ends_at = ?, image_alt = ?,
                content_version = content_version + ?
             WHERE id = ?',
            [...$this->fieldValues($input, $bodyHtml), $bumpVersion ? 1 : 0, $id],
        );
    }

    public function activate(int $id): void
    {
        // Switch the old one off first, or the unique active_key index would refuse the second active row.
        $this->db->execute('UPDATE popups SET is_active = 0 WHERE is_active = 1 AND id <> ?', [$id]);
        $this->db->execute('UPDATE popups SET is_active = 1 WHERE id = ?', [$id]);
    }

    public function deactivate(int $id): void
    {
        $this->db->execute('UPDATE popups SET is_active = 0 WHERE id = ?', [$id]);
    }

    public function putImage(int $id, string $mime, string $bytes): void
    {
        $statement = $this->db->pdo()->prepare(
            'UPDATE popups SET image_mime = :mime, image = :image, image_version = image_version + 1 WHERE id = :id',
        );
        $statement->bindValue('mime', $mime);
        $statement->bindValue('image', $bytes, PDO::PARAM_LOB);
        $statement->bindValue('id', $id, PDO::PARAM_INT);
        $statement->execute();
    }

    public function removeImage(int $id): void
    {
        $this->db->execute('UPDATE popups SET image_mime = NULL, image = NULL WHERE id = ?', [$id]);
    }

    public function image(int $id): ?array
    {
        $row = $this->db->fetchOne('SELECT image_mime, image FROM popups WHERE id = ? AND image_mime IS NOT NULL', [$id]);

        return $row === null ? null : ['mime_type' => (string) $row['image_mime'], 'content' => (string) $row['image']];
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM popups WHERE id = ?', [$id]);
    }

    /** @return list<mixed> */
    private function fieldValues(PopupInput $input, string $bodyHtml): array
    {
        return [
            $input->name, $input->heading, $bodyHtml, $input->buttonLabel, $input->buttonUrl, $input->trigger->value,
            $input->delaySeconds, DbTime::toDb($input->startsAt), DbTime::toDb($input->endsAt), $input->imageAlt,
        ];
    }

    /** @param array<string, mixed> $row */
    private static function map(array $row): Popup
    {
        return new Popup(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['heading'],
            (string) $row['body_html'],
            $row['button_label'] === null ? null : (string) $row['button_label'],
            $row['button_url'] === null ? null : (string) $row['button_url'],
            PopupTrigger::from((string) $row['trigger_type']),
            (int) $row['delay_seconds'],
            DbTime::fromDb($row['starts_at']),
            DbTime::fromDb($row['ends_at']),
            (int) $row['is_active'] === 1,
            (int) $row['content_version'],
            (bool) $row['has_image'],
            (int) $row['image_version'],
            (string) $row['image_alt'],
            DbTime::fromDb($row['updated_at']) ?? new DateTimeImmutable(),
        );
    }
}
