<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Console\CommandInterface;
use App\Console\Io;
use App\Core\Database\Migrator;

final class MigrateCommand implements CommandInterface
{
    public function __construct(private readonly Migrator $migrator)
    {
    }

    public function name(): string
    {
        return 'migrate';
    }

    public function description(): string
    {
        return 'Apply pending database migrations (--status to list them without applying)';
    }

    public function run(array $args, Io $io): int
    {
        if (in_array('--status', $args, true)) {
            $pending = array_keys($this->migrator->pending());
            $io->line($pending === [] ? 'Nothing pending.' : 'Pending: ' . implode(', ', $pending));

            return 0;
        }

        $applied = $this->migrator->migrate(static fn (string $version) => $io->line("Applied {$version}"));
        $io->line($applied === [] ? 'Nothing to migrate.' : sprintf('Done: %d migration(s) applied.', count($applied)));

        return 0;
    }
}
