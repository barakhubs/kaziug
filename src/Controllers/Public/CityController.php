<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;

/** /jobs-in/{city}: an indexable landing page for every city with open jobs. */
final class CityController
{
    public function __construct(
        private readonly JobListPresenter $presenter,
        private readonly Responder $responder,
    ) {
    }

    public function show(Request $request): Response
    {
        $city = $this->presenter->city((string) $request->attribute('slug'));

        return $this->responder->view('public/jobs/index', $this->presenter->build($request, null, $city));
    }
}
