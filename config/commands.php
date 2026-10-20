<?php

declare(strict_types=1);

use App\Console\Commands\ConfigCheckCommand;
use App\Console\Commands\MigrateCommand;

return [
    MigrateCommand::class,
    ConfigCheckCommand::class,
];
