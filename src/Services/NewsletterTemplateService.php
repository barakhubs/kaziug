<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\DomainException;
use App\Domain\NewsletterDraft;
use App\Services\Contracts\JobSearchServiceInterface;
use App\Services\Contracts\NewsletterTemplateServiceInterface;
use App\Services\Contracts\SettingsReaderInterface;
use App\Services\NewsletterTemplates\NewsletterTemplateInterface;

final class NewsletterTemplateService implements NewsletterTemplateServiceInterface
{
    private const CANDIDATES = 20;

    /** @param list<NewsletterTemplateInterface> $templates */
    public function __construct(
        private readonly array $templates,
        private readonly JobSearchServiceInterface $jobs,
        private readonly SettingsReaderInterface $settings,
        private readonly HtmlSanitizer $sanitizer,
        private readonly string $appUrl,
    ) {
    }

    public function templates(): array
    {
        return $this->templates;
    }

    public function candidateJobs(): array
    {
        return $this->jobs->latest(self::CANDIDATES);
    }

    public function compose(string $templateKey, array $jobIds): NewsletterDraft
    {
        $template = $this->find($templateKey) ?? throw new DomainException('Choose a template.');
        $jobs = $this->pickedJobs($template, $jobIds);
        $draft = $template->draft($jobs, $this->settings->site(), $this->appUrl);

        // Sanitised now so the editor and preview show exactly what saving will keep.
        return new NewsletterDraft($draft->subject, $draft->preheader, $this->sanitizer->sanitize($draft->bodyHtml));
    }

    private function find(string $key): ?NewsletterTemplateInterface
    {
        foreach ($this->templates as $template) {
            if ($template->key() === $key) {
                return $template;
            }
        }

        return null;
    }

    /**
     * Only jobs that are live right now and were offered in the picker; newest first.
     * @param list<int> $jobIds
     * @return list<\App\Domain\Job>
     */
    private function pickedJobs(NewsletterTemplateInterface $template, array $jobIds): array
    {
        if ($template->jobSelection() === NewsletterTemplateInterface::JOBS_NONE) {
            return [];
        }
        $wanted = array_flip(array_map('intval', $jobIds));
        $jobs = array_values(array_filter($this->candidateJobs(), static fn ($job): bool => isset($wanted[$job->id])));

        return match (true) {
            $jobs === [] => throw new DomainException('Pick at least one job.'),
            $template->jobSelection() === NewsletterTemplateInterface::JOBS_ONE && count($jobs) !== 1 => throw new DomainException('Pick exactly one job.'),
            default => $jobs,
        };
    }
}
