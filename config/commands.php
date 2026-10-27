<?php

declare(strict_types=1);

use App\Console\Commands\AdminCreateCommand;
use App\Console\Commands\AnalyticsRollupCommand;
use App\Console\Commands\ConfigCheckCommand;
use App\Console\Commands\MigrateCommand;
use App\Console\Commands\NewsletterWorkCommand;
use App\Console\Commands\SeedCommand;

return [
    MigrateCommand::class,
    SeedCommand::class,
    AdminCreateCommand::class,
    NewsletterWorkCommand::class,
    AnalyticsRollupCommand::class,
    ConfigCheckCommand::class,
];
