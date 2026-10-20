<?php

declare(strict_types=1);

namespace App\Console\Seeders;

use App\Console\Io;

interface SeederInterface
{
    /** Short name for `seed --only=<name>`. */
    public function name(): string;

    /** Must be idempotent: running it twice adds nothing the second time. */
    public function run(Io $io): void;
}
