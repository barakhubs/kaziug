<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Services\Contracts\HealthCheckInterface;

final class HealthController
{
    public function __construct(private readonly HealthCheckInterface $health)
    {
    }

    public function show(Request $request): Response
    {
        $result = $this->health->check();

        return Response::json(
            ['status' => $result['ok'] ? 'ok' : 'fail', 'checks' => $result['checks']],
            $result['ok'] ? 200 : 503,
        )->setHeader('Cache-Control', 'no-store');
    }
}
