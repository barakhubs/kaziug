<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Console\Io;
use App\Console\Seeders\SeederInterface;
use App\Core\Config;
use App\Core\Container;

/**
 * Loads sample data. Never runs automatically (not on deploy, not on migrate);
 * refuses to run in production unless --force is given.
 */
final class SeedCommand implements CommandInterface
{
    public function __construct(
        private readonly Container $container,
        private readonly Config $config,
    ) {
    }

    public function name(): string
    {
        return 'seed';
    }

    public function description(): string
    {
        return 'Load sample jobs, categories and social profiles (--only=<name>, --force in production)';
    }

    public function run(array $args, Io $io): int
    {
        if ($this->config->isProduction() && !in_array('--force', $args, true)) {
            $io->error('Refusing to seed sample data in production. Re-run with --force if you really want it.');

            return 1;
        }

        $only = null;
        foreach ($args as $arg) {
            if (str_starts_with($arg, '--only=')) {
                $only = substr($arg, 7);
            }
        }

        /** @var list<class-string<SeederInterface>> $classes */
        $classes = require dirname(__DIR__, 3) . '/config/seeders.php';
        $ran = 0;
        foreach ($classes as $class) {
            /** @var SeederInterface $seeder */
            $seeder = $this->container->get($class);
            if ($only === null || $seeder->name() === $only) {
                $seeder->run($io);
                $ran++;
            }
        }

        if ($ran === 0) {
            $io->error("No seeder named {$only}.");

            return 1;
        }

        return 0;
    }
}
