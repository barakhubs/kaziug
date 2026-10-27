<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Services\Contracts\DashboardServiceInterface;

final class DashboardController
{
    public function __construct(
        private readonly DashboardServiceInterface $dashboard,
        private readonly AnalyticsPresenter $analytics,
        private readonly Responder $responder,
    ) {
    }

    public function index(Request $request): Response
    {
        $summary = $this->dashboard->summary();

        return $this->responder->view('admin/dashboard', [...$summary, 'analyticsTiles' => $this->analytics->tiles($summary['analytics'])]);
    }
}
