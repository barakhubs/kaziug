<?php

declare(strict_types=1);

namespace App\Core\Database;

use RuntimeException;

/**
 * Applies database/migrations/NNNN_name.sql in order, one statement at a time.
 * MySQL commits schema changes (CREATE, ALTER, DROP) immediately, so a migration cannot be rolled
 * back as a whole: its schema_migrations row is written only after every statement succeeded, and
 * a migration that fails halfway must be cleaned up by hand using its rollback note.
 * A named lock stops two deploys from migrating at the same time.
 */
final class Migrator
{
    private const LOCK_NAME = 'kaziug_migrate';

    public function __construct(
        private readonly Database $db,
        private readonly string $directory,
    ) {
    }

    /** @return list<string> versions applied by this run */
    public function migrate(?callable $onApply = null): array
    {
        $this->ensureTable();
        if ((int) $this->db->fetchValue('SELECT GET_LOCK(?, 60)', [self::LOCK_NAME]) !== 1) {
            throw new RuntimeException('Another migration is running. Try again in a minute.');
        }

        try {
            $applied = [];
            foreach ($this->pending() as $version => $file) {
                foreach (self::statements((string) file_get_contents($file)) as $statement) {
                    $this->db->unprepared($statement);
                }
                $this->db->execute('INSERT INTO schema_migrations (version) VALUES (?)', [$version]);
                $applied[] = $version;
                if ($onApply !== null) {
                    $onApply($version);
                }
            }

            return $applied;
        } finally {
            $this->db->fetchValue('SELECT RELEASE_LOCK(?)', [self::LOCK_NAME]);
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

    /**
     * Splits a migration into statements on semicolons outside quotes and comments.
     * PDO only reports an error in the first statement of a multi-statement string, so each runs alone.
     * @return list<string>
     */
    public static function statements(string $sql): array
    {
        $statements = [];
        $current = '';
        $quote = null;
        $length = strlen($sql);

        for ($i = 0; $i < $length; $i++) {
            $char = $sql[$i];
            if ($quote !== null) {
                $current .= $char;
                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $sql[++$i];
                } elseif ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($char === '-' && ($sql[$i + 1] ?? '') === '-') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end;
                continue;
            }
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
            }
            if ($char === ';') {
                $statements[] = trim($current);
                $current = '';
                continue;
            }
            $current .= $char;
        }
        $statements[] = trim($current);

        return array_values(array_filter($statements, static fn (string $s): bool => $s !== ''));
    }

    private function ensureTable(): void
    {
        $this->db->unprepared(
            'CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(191) NOT NULL PRIMARY KEY,
                applied_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
        );
    }
}
