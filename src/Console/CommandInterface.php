<?php

declare(strict_types=1);

namespace App\Console;

interface CommandInterface
{
    public function name(): string;

    public function description(): string;

    /**
     * @param list<string> $args arguments and --options after the command name
     * @return int exit code
     */
    public function run(array $args, Io $io): int;
}
