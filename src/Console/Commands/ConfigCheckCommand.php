<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Console\Io;
use App\Core\AppKey;
use App\Core\Config;
use App\Core\Database\Database;
use App\Core\Database\Migrator;
use Throwable;

/**
 * Checks the runtime before a deploy goes live: extensions, env vars, database, storage.
 */
final class ConfigCheckCommand implements CommandInterface
{
    private const EXTENSIONS = ['pdo_mysql', 'sodium', 'dom', 'fileinfo', 'mbstring', 'json'];

    public function __construct(
        private readonly Config $config,
        private readonly Database $db,
        private readonly Migrator $migrator,
    ) {
    }

    public function name(): string
    {
        return 'config:check';
    }

    public function description(): string
    {
        return 'Check PHP extensions, environment variables, database and storage';
    }

    public function run(array $args, Io $io): int
    {
        $failures = 0;
        $check = static function (bool $ok, string $label, string $hint = '') use ($io, &$failures): void {
            $io->line(($ok ? '[ok]   ' : '[FAIL] ') . $label . (!$ok && $hint !== '' ? " ({$hint})" : ''));
            if (!$ok) {
                $failures++;
            }
        };

        $check(PHP_VERSION_ID >= 80200, 'PHP ' . PHP_VERSION . ' (8.2+ required)');
        foreach (self::EXTENSIONS as $extension) {
            $check(extension_loaded($extension), "extension {$extension}");
        }

        $env = $this->config->string('app.env');
        $check(in_array($env, ['local', 'production', 'testing'], true), "APP_ENV={$env}", 'use local, production or testing');
        $check(AppKey::isValid($this->config->string('app.key')), 'APP_KEY is set and valid', 'see .env.example for how to generate one');
        $url = $this->config->string('app.url');
        $check(filter_var($url, FILTER_VALIDATE_URL) !== false, "APP_URL={$url}");
        if ($env === 'production') {
            $check(str_starts_with($url, 'https://'), 'APP_URL uses https in production');
            $check(!$this->config->bool('app.debug'), 'debug output is off');
        }

        $storage = $this->config->string('paths.storage');
        $check(is_dir($storage . '/logs') && is_writable($storage . '/logs'), 'storage/logs is writable');

        try {
            $version = (string) $this->db->fetchValue('SELECT VERSION()');
            $mariaDb = stripos($version, 'mariadb') !== false;
            $supported = version_compare(strtok($version, '-') ?: '0', $mariaDb ? '10.6' : '8.0', '>=');
            $check($supported, "database reachable ({$version}; MySQL 8.0+ or MariaDB 10.6+ required)");
            $pending = count($this->migrator->pending());
            $check($pending === 0, 'migrations up to date', "{$pending} pending: run php bin/console migrate");
        } catch (Throwable $e) {
            $check(false, 'database reachable', $e->getMessage());
        }

        $io->line($failures === 0 ? 'All checks passed.' : "{$failures} check(s) failed.");

        return $failures === 0 ? 0 : 1;
    }
}
