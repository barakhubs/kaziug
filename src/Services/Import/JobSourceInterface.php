<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * A supplier of jobs to import. Adding a new source (a CSV file, a partner feed) means
 * adding a class that implements this; JobImporter does not change.
 */
interface JobSourceInterface
{
    public function name(): string;

    /** @return iterable<JobImportRecord> */
    public function records(): iterable;
}
