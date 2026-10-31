<?php

declare(strict_types=1);

namespace App\Services\NewsletterTemplates;

use App\Domain\Job;
use App\Domain\NewsletterDraft;
use App\Domain\SiteSettings;

/**
 * A starting point for a newsletter. Templates return HTML built only from the tags the
 * newsletter sanitiser allows (no classes or styles); the email layout styles it at send time.
 * To add one, write a class and list it in config/container.php.
 */
interface NewsletterTemplateInterface
{
    public const JOBS_NONE = 'none';
    public const JOBS_ONE = 'one';
    public const JOBS_MANY = 'many';

    /** Stable identifier used in forms, e.g. "latest-jobs". */
    public function key(): string;

    public function name(): string;

    public function description(): string;

    /** How many jobs the admin picks: JOBS_NONE, JOBS_ONE or JOBS_MANY. */
    public function jobSelection(): string;

    /** @param list<Job> $jobs the picked jobs (already checked to be live), newest first */
    public function draft(array $jobs, SiteSettings $site, string $appUrl): NewsletterDraft;
}
