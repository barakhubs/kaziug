<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Domain\DomainException;
use App\Domain\Job;
use App\Domain\NewsletterDraft;
use App\Services\NewsletterTemplates\NewsletterTemplateInterface;

interface NewsletterTemplateServiceInterface
{
    /** How many of the newest jobs the "Latest jobs" picker ticks by default. */
    public const PRESELECTED_JOBS = 8;

    /** @return list<NewsletterTemplateInterface> */
    public function templates(): array;

    /** @return list<Job> the most recently posted live jobs to choose from, newest first */
    public function candidateJobs(): array;

    /**
     * Builds a sanitised draft from a template and the picked job ids. Nothing is saved.
     * @param list<int> $jobIds
     * @throws DomainException unknown template, or a job selection the template can't use
     */
    public function compose(string $templateKey, array $jobIds): NewsletterDraft;
}
