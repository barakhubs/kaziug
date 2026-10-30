<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\Config;
use App\Core\Container;
use App\Core\Database\Database;
use App\Core\Database\Migrator;
use App\Mail\TransportFactoryInterface;
use PHPUnit\Framework\TestCase;
use Tests\Support\Fakes\RecordingTransportFactory;
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

    /** Every email the app tries to send in a test lands here. */
    protected RecordingTransportFactory $mail;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = require dirname(__DIR__, 2) . '/config/bootstrap.php';
        $this->mail = new RecordingTransportFactory();
        $this->container->instance(TransportFactoryInterface::class, $this->mail);

        // Tests seed from a fixed fixture so editing the real sample jobs never breaks them.
        $config = $this->container->get(Config::class)->with('paths.seeds', dirname(__DIR__) . '/fixtures/seeds');
        $this->container->instance(Config::class, $config);
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
        $tables = array_column($this->db->fetchAll(
            "SELECT table_name AS t FROM information_schema.tables
             WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE' AND table_name <> 'schema_migrations'",
        ), 't');
        // TRUNCATE resets AUTO_INCREMENT; foreign keys must be off because MySQL has no TRUNCATE ... CASCADE.
        $this->db->unprepared('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($tables as $table) {
            $this->db->unprepared("TRUNCATE TABLE `{$table}`");
        }
        $this->db->unprepared('SET FOREIGN_KEY_CHECKS = 1');

        // Restore default settings rows; the migration's INSERT is idempotent.
        $this->db->unprepared((string) file_get_contents($config->string('paths.migrations') . '/0002_default_settings.sql'));
    }
}
