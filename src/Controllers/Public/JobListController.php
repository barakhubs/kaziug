<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;

final class JobListController
{
    public function __construct(
        private readonly JobListPresenter $presenter,
        private readonly Responder $responder,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->responder->view('public/jobs/index', $this->presenter->build($request));
    }
}
