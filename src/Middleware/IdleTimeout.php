<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Services\AdminGuard;

/**
 * Signs the admin out after SESSION_IDLE_MINUTES without a request. Runs before Authenticate.
 */
final class IdleTimeout implements MiddlewareInterface
{
    // Avoids rewriting the session row on every click.
    private const TOUCH_SECONDS = 30;

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly Config $config,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        /** @var Session $session */
        $session = $request->attribute('session');
        if (!$this->guard->isSignedIn($request)) {
            return $next($request);
        }

        $idleSeconds = $this->config->int('session.idle_minutes', 30) * 60;
        $lastSeen = (int) $session->get(AdminGuard::KEY_LAST_SEEN, 0);

        if (time() - $lastSeen > $idleSeconds) {
            $this->guard->logout($request);
            $session->flash('info', 'You were signed out after a period of inactivity.');

            return Response::redirect('/admin/login');
        }

        if (time() - $lastSeen > self::TOUCH_SECONDS) {
            $session->set(AdminGuard::KEY_LAST_SEEN, time());
        }

        return $next($request);
    }
}
