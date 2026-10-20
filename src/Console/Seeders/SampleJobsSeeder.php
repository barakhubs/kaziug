<?php

declare(strict_types=1);

namespace App\Console\Seeders;

use App\Console\Io;
use App\Core\Config;
use App\Domain\Actor;
use App\Services\Import\JobImporter;
use App\Services\Import\SampleJobSource;

/** The prototype's 14 sample jobs and their categories. */
final class SampleJobsSeeder implements SeederInterface
{
    public function __construct(
        private readonly JobImporter $importer,
        private readonly Config $config,
    ) {
    }

    public function name(): string
    {
        return 'jobs';
    }

    public function run(Io $io): void
    {
        $source = new SampleJobSource($this->config->string('paths.seeds') . '/sample_jobs.json');
        $result = $this->importer->import($source, Actor::console());
        $io->line("Jobs: {$result['created']} created, {$result['skipped']} already present.");
    }
}
