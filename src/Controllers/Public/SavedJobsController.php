<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Services\Contracts\JobSearchServiceInterface;

/**
 * Saved jobs live only in the visitor's browser (localStorage). The page script sends their
 * ids here as ?ids=1,2,3; the server just renders whichever of them are still visible.
 */
final class SavedJobsController
{
    public function __construct(
        private readonly JobSearchServiceInterface $search,
        private readonly Responder $responder,
    ) {
    }

    public function index(Request $request): Response
    {
        $raw = $request->query('ids');
        $ids = is_string($raw)
            ? array_values(array_filter(array_map('intval', explode(',', $raw)), static fn (int $id): bool => $id > 0))
            : [];

        $response = $this->responder->view('public/saved', [
            'jobs' => $this->search->savedJobs($ids),
            'requested' => $ids !== [],
        ]);

        return $response->setHeader('X-Robots-Tag', 'noindex');
    }
}
