<?php

declare(strict_types=1);

namespace App\Core\Database;

use RuntimeException;

/**
 * Applies database/migrations/NNNN_name.sql in order. Each file runs in its own
 * transaction together with its schema_migrations row, so a failed file leaves no trace.
 * An advisory lock stops two deploys from migrating at the same time.
 */
final class Migrator
{
    private const LOCK_ID = 727_001;

    public function __construct(
        private readonly Database $db,
        private readonly string $directory,
    ) {
    }

    /** @return list<string> versions applied by this run */
    public function migrate(?callable $onApply = null): array
    {
        $this->ensureTable();
        $this->db->fetchValue('SELECT pg_advisory_lock(?)', [self::LOCK_ID]);

        try {
            $applied = [];
            foreach ($this->pending() as $version => $file) {
                $sql = (string) file_get_contents($file);
                $this->db->transaction(function (Database $db) use ($sql, $version): void {
                    $db->unprepared($sql);
                    $db->execute('INSERT INTO schema_migrations (version) VALUES (?)', [$version]);
                });
                $applied[] = $version;
                if ($onApply !== null) {
                    $onApply($version);
                }
            }

            return $applied;
        } finally {
            $this->db->fetchValue('SELECT pg_advisory_unlock(?)', [self::LOCK_ID]);
        }
    }

    /** @return array<string, string> version => file path, not yet applied */
    public function pending(): array
    {
        $this->ensureTable();
        $done = array_flip(array_column($this->db->fetchAll('SELECT version FROM schema_migrations'), 'version'));

        return array_diff_key($this->available(), $done);
    }

    /** @return array<string, string> */
    public function available(): array
    {
        $files = glob($this->directory . '/*.sql') ?: [];
        sort($files, SORT_STRING);

        $migrations = [];
        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (preg_match('/^\d{4}_[a-z0-9_]+$/', $version) !== 1) {
                throw new RuntimeException("Migration file name must look like 0001_name.sql: {$version}");
            }
            $migrations[$version] = $file;
        }

        return $migrations;
    }

    private function ensureTable(): void
    {
        $this->db->unprepared(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version text PRIMARY KEY,
                applied_at timestamptz NOT NULL DEFAULT now()
            )',
        );
    }
}
