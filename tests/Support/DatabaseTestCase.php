<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Config;
use App\Core\Container;
use App\Core\Database\Database;
use App\Core\Database\Migrator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Boots the app against the test database (DB_TEST_DATABASE), migrates it once per run
 * and empties every table before each test.
 */
abstract class DatabaseTestCase extends TestCase
{
    private static bool $migrated = false;

    protected Container $container;

    protected Database $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = require dirname(__DIR__, 2) . '/config/bootstrap.php';

        $config = $this->container->get(Config::class);
        if ($config->string('app.env') !== 'testing' || !str_ends_with($config->string('db.database'), '_test')) {
            throw new RuntimeException('Refusing to run database tests outside a *_test database.');
        }

        $this->db = $this->container->get(Database::class);
        if (!self::$migrated) {
            $this->container->get(Migrator::class)->migrate();
            self::$migrated = true;
        }
        $this->resetDatabase($config);
    }

    private function resetDatabase(Config $config): void
    {
        $tables = $this->db->fetchAll(
            "SELECT tablename FROM pg_tables WHERE schemaname = 'public' AND tablename <> 'schema_migrations'",
        );
        $list = implode(', ', array_map(static fn (array $t): string => '"' . $t['tablename'] . '"', $tables));
        $this->db->unprepared("TRUNCATE {$list} RESTART IDENTITY CASCADE");

        // Restore default settings rows; the migration's INSERT is idempotent.
        $this->db->unprepared((string) file_get_contents($config->string('paths.migrations') . '/0011_settings.sql'));
    }
}
