<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Repositories\Contracts\SettingsRepositoryInterface;
use PDO;

final class MysqlSettingsRepository implements SettingsRepositoryInterface
{
    public function __construct(private readonly Database $db)
    {
    }

    public function all(): array
    {
        $values = [];
        foreach ($this->db->fetchAll('SELECT `key`, value FROM settings') as $row) {
            $values[(string) $row['key']] = json_decode((string) $row['value'], true);
        }

        return $values;
    }

    public function setMany(array $values): void
    {
        $this->db->transaction(function (Database $db) use ($values): void {
            foreach ($values as $key => $value) {
                $db->execute(
                    'INSERT INTO settings (`key`, value, updated_at) VALUES (?, ?, NOW(6))
                     ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW(6)',
                    [$key, json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE)],
                );
            }
        });
    }

    public function assetVersion(string $key): ?string
    {
        $value = $this->db->fetchValue('SELECT FLOOR(UNIX_TIMESTAMP(updated_at)) FROM site_assets WHERE `key` = ?', [$key]);

        return $value === null ? null : (string) $value;
    }

    public function asset(string $key): ?array
    {
        $statement = $this->db->pdo()->prepare(
            'SELECT mime_type, content, FLOOR(UNIX_TIMESTAMP(updated_at)) AS version FROM site_assets WHERE `key` = ?',
        );
        $statement->execute([$key]);
        $statement->bindColumn('mime_type', $mime);
        $statement->bindColumn('content', $content, PDO::PARAM_LOB);
        $statement->bindColumn('version', $version);
        if ($statement->fetch(PDO::FETCH_BOUND) === false) {
            return null;
        }

        return [
            'mime_type' => (string) $mime,
            'content' => is_resource($content) ? (string) stream_get_contents($content) : (string) $content,
            'version' => (string) $version,
        ];
    }

    public function putAsset(string $key, string $mimeType, string $content): void
    {
        $statement = $this->db->pdo()->prepare(
            'INSERT INTO site_assets (`key`, mime_type, content, updated_at) VALUES (:key, :mime, :content, NOW(6))
             ON DUPLICATE KEY UPDATE mime_type = VALUES(mime_type), content = VALUES(content), updated_at = NOW(6)',
        );
        $statement->bindValue('key', $key);
        $statement->bindValue('mime', $mimeType);
        $statement->bindValue('content', $content, PDO::PARAM_LOB);
        $statement->execute();
    }

    public function deleteAsset(string $key): void
    {
        $this->db->execute('DELETE FROM site_assets WHERE `key` = ?', [$key]);
    }
}
