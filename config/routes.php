<?php

declare(strict_types=1);

use App\Controllers\Admin\AccountController;
use App\Controllers\Admin\AuditLogController;
use App\Controllers\Admin\AuthController;
use App\Controllers\Admin\CategoriesController;
use App\Controllers\Admin\DashboardController;
use App\Controllers\Admin\JobsController;
use App\Controllers\Admin\NewslettersController;
use App\Controllers\Admin\SiteSettingsController;
use App\Controllers\Admin\SmtpSettingsController;
use App\Controllers\Admin\SocialProfilesController;
use App\Controllers\Admin\SubscribersController;
use App\Controllers\Public\SubscriptionController;
use App\Controllers\Public\CategoryController;
use App\Controllers\Public\ContactController;
use App\Controllers\Public\HealthController;
use App\Controllers\Public\JobListController;
use App\Controllers\Public\JobShowController;
use App\Controllers\Public\MediaController;
use App\Controllers\Public\SavedJobsController;
use App\Core\Http\Router;
use App\Middleware\Authenticate;
use App\Middleware\IdleTimeout;
use App\Middleware\SharePublicData;

/*
 * Every route in the application. There is deliberately no registration or
 * user-creation route: the admin is created with `php bin/console admin:create`.
 */
return static function (Router $r): void {
    // --- Public pages ---------------------------------------------------------------------
    $r->group('', [SharePublicData::class], function (Router $r): void {
        $r->get('/', [JobListController::class, 'index']);
        $r->get('/jobs/{slug:[a-z0-9-]+}', [JobShowController::class, 'show']);
        $r->get('/categories/{slug:[a-z0-9-]+}', [CategoryController::class, 'show']);
        $r->get('/saved', [SavedJobsController::class, 'index']);

        $r->get('/contact', [ContactController::class, 'showContact']);
        $r->post('/contact', [ContactController::class, 'sendContact']);
        $r->get('/cv-help', [ContactController::class, 'showCvHelp']);
        $r->post('/cv-help', [ContactController::class, 'sendCvHelp']);

        $r->post('/subscribe', [SubscriptionController::class, 'subscribe']);
        $r->get('/subscribe/confirm/{token:[A-Za-z0-9_-]+}', [SubscriptionController::class, 'showConfirm']);
        $r->post('/subscribe/confirm/{token:[A-Za-z0-9_-]+}', [SubscriptionController::class, 'confirm']);
        $r->get('/unsubscribe/{token:[A-Za-z0-9_-]+}', [SubscriptionController::class, 'showUnsubscribe']);
        $r->post('/unsubscribe/{token:[A-Za-z0-9_-]+}', [SubscriptionController::class, 'unsubscribe']);
    });

    // --- Public machine endpoints -----------------------------------------------------------
    $r->get('/health', [HealthController::class, 'show']);
    $r->get('/media/logo', [MediaController::class, 'logo']);

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
        $r->get('/jobs/{id:\d+}', [JobsController::class, 'show']);
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

        $r->get('/settings/social', [SocialProfilesController::class, 'index']);
        $r->get('/settings/social/new', [SocialProfilesController::class, 'create']);
        $r->post('/settings/social', [SocialProfilesController::class, 'store']);
        $r->get('/settings/social/{id:\d+}/edit', [SocialProfilesController::class, 'edit']);
        $r->post('/settings/social/{id:\d+}', [SocialProfilesController::class, 'update']);
        $r->post('/settings/social/{id:\d+}/visibility', [SocialProfilesController::class, 'visibility']);
        $r->post('/settings/social/{id:\d+}/move', [SocialProfilesController::class, 'move']);
        $r->post('/settings/social/{id:\d+}/delete', [SocialProfilesController::class, 'delete']);

        $r->get('/newsletters', [NewslettersController::class, 'index']);
        $r->get('/newsletters/new', [NewslettersController::class, 'create']);
        $r->post('/newsletters', [NewslettersController::class, 'store']);
        $r->get('/newsletters/{id:\d+}', [NewslettersController::class, 'show']);
        $r->get('/newsletters/{id:\d+}/edit', [NewslettersController::class, 'edit']);
        $r->post('/newsletters/{id:\d+}', [NewslettersController::class, 'update']);
        $r->get('/newsletters/{id:\d+}/preview', [NewslettersController::class, 'preview']);
        $r->post('/newsletters/{id:\d+}/test', [NewslettersController::class, 'test']);
        $r->post('/newsletters/{id:\d+}/send', [NewslettersController::class, 'send']);
        $r->post('/newsletters/{id:\d+}/schedule', [NewslettersController::class, 'schedule']);
        $r->post('/newsletters/{id:\d+}/unschedule', [NewslettersController::class, 'unschedule']);
        $r->post('/newsletters/{id:\d+}/delete', [NewslettersController::class, 'delete']);

        $r->get('/subscribers', [SubscribersController::class, 'index']);
        $r->get('/subscribers/export.csv', [SubscribersController::class, 'export']);
        $r->get('/subscribers/import', [SubscribersController::class, 'showImport']);
        $r->post('/subscribers/import', [SubscribersController::class, 'import']);
        $r->post('/subscribers/{id:\d+}/unsubscribe', [SubscribersController::class, 'unsubscribe']);
        $r->post('/subscribers/{id:\d+}/delete', [SubscribersController::class, 'delete']);

        $r->get('/settings/smtp', [SmtpSettingsController::class, 'show']);
        $r->post('/settings/smtp', [SmtpSettingsController::class, 'update']);
        $r->post('/settings/smtp/test', [SmtpSettingsController::class, 'test']);

        $r->get('/settings', [SiteSettingsController::class, 'show']);
        $r->post('/settings', [SiteSettingsController::class, 'update']);

        $r->get('/audit', [AuditLogController::class, 'index']);

        $r->get('/account', [AccountController::class, 'show']);
        $r->post('/account/password', [AccountController::class, 'updatePassword']);
        $r->post('/account/logout-all', [AccountController::class, 'logoutEverywhere']);
    });
};
