<?php

declare(strict_types=1);

use App\Controllers\Public\HealthController;
use App\Core\Http\Router;

return static function (Router $r): void {
    // --- Public -------------------------------------------------------------------------
    $r->get('/health', [HealthController::class, 'show']);
};
