<?php

declare(strict_types=1);

use App\Controllers\Admin\AccountController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\CategoriesController;
use App\Controllers\Admin\JobsController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Public\CategoryController;
use App\Controllers\Public\HealthController;
use App\Controllers\Public\JobListController;
use App\Controllers\Public\JobShowController;
use App\Controllers\Public\SavedJobsController;
use App\Core\Http\Router;
use App\Middleware\Authenticate;
use App\Middleware\IdleTimeout;

/*
 * Every route in the application. There is deliberately no registration or
 * user-creation route: the admin is created with `php bin/console admin:create`.
 */
return static function (Router $r): void {
    // --- Public -------------------------------------------------------------------------
    $r->get('/', [JobListController::class, 'index']);
    $r->get('/jobs/{slug:[a-z0-9-]+}', [JobShowController::class, 'show']);
    $r->get('/categories/{slug:[a-z0-9-]+}', [CategoryController::class, 'show']);
    $r->get('/saved', [SavedJobsController::class, 'index']);
    $r->get('/health', [HealthController::class, 'show']);

    // --- Admin: sign-in (no auth) --------------------------------------------------------
    $r->get('/admin/login', [AuthController::class, 'showLogin']);
    $r->post('/admin/login', [AuthController::class, 'login']);

    // --- Admin: everything else ----------------------------------------------------------
    $r->group('/admin', [IdleTimeout::class, Authenticate::class], function (Router $r): void {
        $r->get('', [DashboardController::class, 'index']);
        $r->post('/logout', [AuthController::class, 'logout']);

        $r->get('/jobs', [JobsController::class, 'index']);
        $r->get('/jobs/new', [JobsController::class, 'create']);
        $r->post('/jobs', [JobsController::class, 'store']);
        $r->post('/jobs/bulk-status', [JobsController::class, 'bulkStatus']);
        $r->get('/jobs/{id:\d+}/edit', [JobsController::class, 'edit']);
        $r->post('/jobs/{id:\d+}', [JobsController::class, 'update']);
        $r->post('/jobs/{id:\d+}/status', [JobsController::class, 'changeStatus']);
        $r->post('/jobs/{id:\d+}/duplicate', [JobsController::class, 'duplicate']);
        $r->post('/jobs/{id:\d+}/delete', [JobsController::class, 'delete']);

        $r->get('/categories', [CategoriesController::class, 'index']);
        $r->get('/categories/new', [CategoriesController::class, 'create']);
        $r->post('/categories', [CategoriesController::class, 'store']);
        $r->get('/categories/{id:\d+}/edit', [CategoriesController::class, 'edit']);
        $r->post('/categories/{id:\d+}', [CategoriesController::class, 'update']);
        $r->post('/categories/{id:\d+}/toggle', [CategoriesController::class, 'toggle']);
        $r->post('/categories/{id:\d+}/move', [CategoriesController::class, 'move']);
        $r->post('/categories/{id:\d+}/delete', [CategoriesController::class, 'delete']);

        $r->get('/account', [AccountController::class, 'show']);
        $r->post('/account/password', [AccountController::class, 'updatePassword']);
        $r->post('/account/logout-all', [AccountController::class, 'logoutEverywhere']);
    });
};
