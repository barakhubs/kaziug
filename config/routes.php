<?php

declare(strict_types=1);

use App\Controllers\Admin\AccountController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Public\HealthController;
use App\Core\Http\Router;
use App\Middleware\Authenticate;
use App\Middleware\IdleTimeout;

/*
 * Every route in the application. There is deliberately no registration or
 * user-creation route: the admin is created with `php bin/console admin:create`.
 */
return static function (Router $r): void {
    // --- Public -------------------------------------------------------------------------
    $r->get('/health', [HealthController::class, 'show']);

    // --- Admin: sign-in (no auth) --------------------------------------------------------
    $r->get('/admin/login', [AuthController::class, 'showLogin']);
    $r->post('/admin/login', [AuthController::class, 'login']);

    // --- Admin: everything else ----------------------------------------------------------
    $r->group('/admin', [IdleTimeout::class, Authenticate::class], function (Router $r): void {
        $r->get('', [DashboardController::class, 'index']);
        $r->post('/logout', [AuthController::class, 'logout']);

        $r->get('/account', [AccountController::class, 'show']);
        $r->post('/account/password', [AccountController::class, 'updatePassword']);
        $r->post('/account/logout-all', [AccountController::class, 'logoutEverywhere']);
    });
};
