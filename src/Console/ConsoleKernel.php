<?php

declare(strict_types=1);

namespace App\Console;

use App\Core\Container;
use App\Core\Log\LoggerInterface;
use Throwable;

/**
 * Dispatches `bin/console <command>`. Commands are listed in config/commands.php,
 * so adding one never requires editing this class.
 */
final class ConsoleKernel
{
    public function __construct(
        private readonly Container $container,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @param list<string> $argv */
    public function run(array $argv, Io $io): int
    {
        $commands = $this->commands();
        $name = $argv[0] ?? 'list';

        if ($name === 'list' || $name === '--help' || $name === '-h') {
            $io->line('KaziUG console. Usage: php bin/console <command> [options]');
            $io->line();
            foreach ($commands as $command) {
                $io->line(sprintf('  %-18s %s', $command->name(), $command->description()));
            }

            return 0;
        }

        foreach ($commands as $command) {
            if ($command->name() === $name) {
                try {
                    return $command->run(array_slice($argv, 1), $io);
                } catch (Throwable $e) {
                    $this->logger->error("Console command {$name} failed", ['error' => $e->getMessage(), 'exception' => $e::class]);
                    $io->error('Error: ' . $e->getMessage());

                    return 1;
                }
            }
        }

        $io->error("Unknown command: {$name}. Run `php bin/console list`.");

        return 1;
    }

    /** @return list<CommandInterface> */
    private function commands(): array
    {
        /** @var list<class-string<CommandInterface>> $classes */
        $classes = require dirname(__DIR__, 2) . '/config/commands.php';

        return array_map(fn (string $class): CommandInterface => $this->container->get($class), $classes);
    }
}
