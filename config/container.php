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
use App\Repositories\Contracts\CategoryRepositoryInterface;
use App\Repositories\Contracts\JobReadRepositoryInterface;
use App\Repositories\Contracts\JobWriteRepositoryInterface;
use App\Repositories\Contracts\SettingsRepositoryInterface;
use App\Repositories\PgSettingsRepository;
use App\Repositories\Contracts\SocialProfileRepositoryInterface;
use App\Repositories\PgSocialProfileRepository;
use App\Services\Contracts\SettingsServiceInterface;
use App\Services\Contracts\SocialProfileServiceInterface;
use App\Services\SettingsService;
use App\Services\SocialProfileService;
use App\Middleware\MaintenanceMode;
use App\Mail\ConfiguredMailer;
use App\Mail\MailerInterface;
use App\Mail\PhpMailerTransportFactory;
use App\Mail\TransportFactoryInterface;
use App\Repositories\Contracts\SmtpSettingsRepositoryInterface;
use App\Repositories\PgSmtpSettingsRepository;
use App\Services\Contracts\SmtpSettingsServiceInterface;
use App\Services\SmtpSettingsService;
use App\Repositories\Contracts\SubscriberRepositoryInterface;
use App\Repositories\PgSubscriberRepository;
use App\Services\Contracts\SubscriberAdminServiceInterface;
use App\Services\Contracts\SubscriptionServiceInterface;
use App\Services\SubscriberAdminService;
use App\Services\SubscriptionService;
use App\Mail\EmailNewsletterChannel;
use App\Mail\NewsletterChannelInterface;
use App\Repositories\Contracts\DeliveryRepositoryInterface;
use App\Repositories\Contracts\LockInterface;
use App\Repositories\Contracts\NewsletterRepositoryInterface;
use App\Repositories\PgAdvisoryLock;
use App\Repositories\PgDeliveryRepository;
use App\Repositories\PgNewsletterRepository;
use App\Services\Contracts\NewsletterServiceInterface;
use App\Services\NewsletterService;
use App\Services\Contracts\JobSearchServiceInterface;
use App\Services\Contracts\SettingsReaderInterface;
use App\Services\JobSearchService;
use App\Services\SettingsReader;
use App\Repositories\Contracts\ThrottleRepositoryInterface;
use App\Repositories\Contracts\TransactionManagerInterface;
use App\Repositories\PgAdminUserRepository;
use App\Repositories\PgAuditLogRepository;
use App\Repositories\PgCategoryRepository;
use App\Repositories\PgJobRepository;
use App\Repositories\PgThrottleRepository;
use App\Repositories\PgTransactionManager;
use App\Services\AdminAccountService;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\CategoryService;
use App\Services\Contracts\AdminAccountServiceInterface;
use App\Services\Contracts\AuditServiceInterface;
use App\Services\Contracts\AuthServiceInterface;
use App\Services\Contracts\CategoryServiceInterface;
use App\Services\Contracts\JobServiceInterface;
use App\Services\JobService;
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
            MaintenanceMode::class,
        ],
    ));

    // --- Repositories -------------------------------------------------------------------
    $c->bind(AdminUserRepositoryInterface::class, PgAdminUserRepository::class);
    $c->bind(ThrottleRepositoryInterface::class, PgThrottleRepository::class);
    $c->bind(AuditLogRepositoryInterface::class, PgAuditLogRepository::class);
    $c->bind(TransactionManagerInterface::class, PgTransactionManager::class);
    $c->bind(CategoryRepositoryInterface::class, PgCategoryRepository::class);
    $c->bind(JobWriteRepositoryInterface::class, PgJobRepository::class);
    $c->bind(JobReadRepositoryInterface::class, PgJobRepository::class);
    $c->bind(SettingsRepositoryInterface::class, PgSettingsRepository::class);
    $c->bind(SocialProfileRepositoryInterface::class, PgSocialProfileRepository::class);
    $c->bind(SmtpSettingsRepositoryInterface::class, PgSmtpSettingsRepository::class);
    $c->bind(SubscriberRepositoryInterface::class, PgSubscriberRepository::class);
    $c->bind(NewsletterRepositoryInterface::class, PgNewsletterRepository::class);
    $c->bind(DeliveryRepositoryInterface::class, PgDeliveryRepository::class);
    $c->bind(LockInterface::class, PgAdvisoryLock::class);

    // --- Mail ---------------------------------------------------------------------------
    $c->bind(TransportFactoryInterface::class, PhpMailerTransportFactory::class);
    $c->bind(MailerInterface::class, ConfiguredMailer::class);
    // To add a channel, bind a composite here that also calls the new one.
    $c->bind(NewsletterChannelInterface::class, EmailNewsletterChannel::class);

    // --- Services -----------------------------------------------------------------------
    $c->bind(HealthCheckInterface::class, DatabaseHealthCheck::class);
    $c->bind(PasswordHasherInterface::class, PasswordHasher::class);
    $c->bind(ThrottleInterface::class, Throttle::class);
    $c->bind(AuditServiceInterface::class, AuditService::class);
    $c->bind(AuthServiceInterface::class, AuthService::class);
    $c->bind(AdminAccountServiceInterface::class, AdminAccountService::class);
    $c->bind(CategoryServiceInterface::class, CategoryService::class);
    $c->bind(JobServiceInterface::class, JobService::class);
    $c->bind(JobSearchServiceInterface::class, JobSearchService::class);
    $c->bind(SettingsReaderInterface::class, SettingsReader::class);
    $c->bind(SettingsServiceInterface::class, SettingsService::class);
    $c->bind(SocialProfileServiceInterface::class, SocialProfileService::class);
    $c->bind(SmtpSettingsServiceInterface::class, SmtpSettingsService::class);
    $c->bind(SubscriptionServiceInterface::class, SubscriptionService::class);
    $c->bind(SubscriberAdminServiceInterface::class, SubscriberAdminService::class);
    $c->bind(NewsletterServiceInterface::class, NewsletterService::class);
};
