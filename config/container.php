<?php

declare(strict_types=1);

use App\Core\AppKey;
use App\Core\Config;
use App\Core\Container;
use App\Core\Database\Database;
use App\Core\Database\Migrator;
use App\Core\ErrorHandler;
use App\Core\Http\Kernel;
use App\Core\Http\MiddlewarePipeline;
use App\Core\Http\Router;
use App\Core\Log\FileLogger;
use App\Core\Log\LoggerInterface;
use App\Core\Session\PgSessionStore;
use App\Core\Session\SessionStoreInterface;
use App\Core\View;
use App\Middleware\CsrfCookie;
use App\Middleware\RenderErrors;
use App\Middleware\SecurityHeaders;
use App\Middleware\ShareViewData;
use App\Middleware\StartSession;
use App\Middleware\VerifyCsrfToken;
use App\Repositories\Contracts\AdminUserRepositoryInterface;
use App\Repositories\Contracts\AuditLogRepositoryInterface;
use App\Repositories\Contracts\ThrottleRepositoryInterface;
use App\Repositories\PgAdminUserRepository;
use App\Repositories\PgAuditLogRepository;
use App\Repositories\PgThrottleRepository;
use App\Services\AdminAccountService;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\Contracts\AdminAccountServiceInterface;
use App\Services\Contracts\AuditServiceInterface;
use App\Services\Contracts\AuthServiceInterface;
use App\Services\Contracts\HealthCheckInterface;
use App\Services\Contracts\PasswordHasherInterface;
use App\Services\Contracts\ThrottleInterface;
use App\Services\DatabaseHealthCheck;
use App\Services\PasswordHasher;
use App\Services\Throttle;

/*
 * The single place where abstractions are mapped to implementations (Dependency Inversion).
 * Concrete classes without a binding here are autowired from their constructor types.
 */
return static function (Container $c, Config $config): void {
    // --- Infrastructure -----------------------------------------------------------------
    $c->set(LoggerInterface::class, fn () => new FileLogger($config->string('log.path'), $config->string('log.level', 'info')));
    $c->set(Database::class, fn () => new Database($config->get('db')));
    $c->set(Migrator::class, fn (Container $c) => new Migrator($c->get(Database::class), $config->string('paths.migrations')));
    $c->set(View::class, fn () => new View($config->string('paths.views')));
    $c->set(AppKey::class, fn () => new AppKey($config->string('app.key')));
    $c->set(ErrorHandler::class, fn (Container $c) => new ErrorHandler(
        $c->get(LoggerInterface::class),
        $c->get(View::class),
        $config->bool('app.debug'),
    ));
    $c->bind(SessionStoreInterface::class, PgSessionStore::class);

    // --- HTTP ---------------------------------------------------------------------------
    $c->set(Router::class, function () {
        $router = new Router();
        (require __DIR__ . '/routes.php')($router);

        return $router;
    });
    $c->set(Kernel::class, fn (Container $c) => new Kernel(
        $c,
        $c->get(Router::class),
        $c->get(MiddlewarePipeline::class),
        $c->get(ErrorHandler::class),
        [
            SecurityHeaders::class,
            StartSession::class,
            CsrfCookie::class,
            ShareViewData::class,
            RenderErrors::class,
            VerifyCsrfToken::class,
        ],
    ));

    // --- Repositories -------------------------------------------------------------------
    $c->bind(AdminUserRepositoryInterface::class, PgAdminUserRepository::class);
    $c->bind(ThrottleRepositoryInterface::class, PgThrottleRepository::class);
    $c->bind(AuditLogRepositoryInterface::class, PgAuditLogRepository::class);

    // --- Services -----------------------------------------------------------------------
    $c->bind(HealthCheckInterface::class, DatabaseHealthCheck::class);
    $c->bind(PasswordHasherInterface::class, PasswordHasher::class);
    $c->bind(ThrottleInterface::class, Throttle::class);
    $c->bind(AuditServiceInterface::class, AuditService::class);
    $c->bind(AuthServiceInterface::class, AuthService::class);
    $c->bind(AdminAccountServiceInterface::class, AdminAccountService::class);
};
