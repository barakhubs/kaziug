<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Services\Contracts\JobSearchServiceInterface;
use App\Services\JobPostingSchema;

final class JobShowController
{
    public function __construct(
        private readonly JobSearchServiceInterface $search,
        private readonly JobPostingSchema $schema,
        private readonly Responder $responder,
    ) {
    }

    public function show(Request $request): Response
    {
        $job = $this->search->findBySlug((string) $request->attribute('slug'));

        return $this->responder->view('public/jobs/show', [
            'job' => $job,
            'jsonLd' => $this->schema->forJob($job),
        ]);
    }
}
