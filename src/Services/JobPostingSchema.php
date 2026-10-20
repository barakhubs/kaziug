<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Domain\Job;
use App\Services\Contracts\SettingsReaderInterface;

/**
 * schema.org JobPosting structured data for a job page (Google for Jobs).
 */
final class JobPostingSchema
{
    public function __construct(
        private readonly Config $config,
        private readonly SettingsReaderInterface $settings,
    ) {
    }

    /** @return string JSON, safe to place inside <script type="application/ld+json"> */
    public function forJob(Job $job): string
    {
        $data = [
            '@context' => 'https://schema.org/',
            '@type' => 'JobPosting',
            'title' => $job->title,
            'description' => $this->description($job),
            'identifier' => ['@type' => 'PropertyValue', 'name' => $this->settings->site()->siteName, 'value' => (string) $job->id],
            'datePosted' => $job->publishedAt?->format('Y-m-d'),
            'employmentType' => $job->jobType->schemaOrg(),
            'hiringOrganization' => ['@type' => 'Organization', 'name' => $job->company],
            'jobLocation' => [
                '@type' => 'Place',
                'address' => ['@type' => 'PostalAddress', 'addressLocality' => $job->location, 'addressCountry' => 'UG'],
            ],
            'url' => $this->config->string('app.url') . '/jobs/' . $job->slug,
        ];
        if ($job->expiresAt !== null) {
            $data['validThrough'] = $job->expiresAt->format(DATE_ATOM);
        }
        if ($job->isRemote) {
            $data['jobLocationType'] = 'TELECOMMUTE';
            $data['applicantLocationRequirements'] = ['@type' => 'Country', 'name' => 'Uganda'];
        }
        if ($job->salaryMin !== null || $job->salaryMax !== null) {
            $data['baseSalary'] = [
                '@type' => 'MonetaryAmount',
                'currency' => $job->currency,
                'value' => array_filter([
                    '@type' => 'QuantitativeValue',
                    'minValue' => $job->salaryMin,
                    'maxValue' => $job->salaryMax,
                    'unitText' => 'MONTH',
                ], static fn (mixed $v): bool => $v !== null),
            ];
        }

        // JSON_HEX_* keeps "</script>" and quotes inert inside the script element.
        return json_encode(
            array_filter($data, static fn (mixed $v): bool => $v !== null),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT,
        );
    }

    private function description(Job $job): string
    {
        $html = '<p>' . e($job->summary) . '</p>';
        foreach (['Responsibilities' => $job->responsibilities, 'Requirements' => $job->requirements] as $heading => $items) {
            if ($items !== []) {
                $html .= '<p><strong>' . $heading . '</strong></p><ul>'
                    . implode('', array_map(static fn (string $i): string => '<li>' . e($i) . '</li>', $items)) . '</ul>';
            }
        }

        return $html;
    }
}
