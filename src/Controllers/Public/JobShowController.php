<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Domain\Slug;
use App\Services\Contracts\JobSearchServiceInterface;
use App\Services\JobPostingSchema;
use App\Services\Seo\StructuredData;

final class JobShowController
{
    private const RELATED = 5;

    public function __construct(
        private readonly JobSearchServiceInterface $search,
        private readonly JobPostingSchema $schema,
        private readonly StructuredData $structuredData,
        private readonly Responder $responder,
    ) {
    }

    public function show(Request $request): Response
    {
        $job = $this->search->findBySlug((string) $request->attribute('slug'));

        return $this->responder->view('public/jobs/show', [
            'job' => $job,
            'jsonLd' => $this->schema->forJob($job),
            'breadcrumbsJsonLd' => $this->structuredData->breadcrumbs([
                ['Jobs', '/'],
                [$job->categoryName . ' jobs', '/categories/' . $job->categorySlug],
                [$job->title, '/jobs/' . $job->slug],
            ]),
            'citySlug' => Slug::from($job->location),
            'related' => $this->search->related($job, self::RELATED),
        ]);
    }
}
