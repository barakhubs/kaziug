<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Csrf;
use App\Core\Http\Request;
use App\Core\Session\Session;
use App\Domain\AdminUser;
use App\Middleware\CsrfCookie;
use App\Services\Contracts\AuthServiceInterface;

/**
 * The session side of admin authentication: who is signed in on this request.
 * Credential checks live in AuthService; this class only reads and writes the session.
 */
final class AdminGuard
{
    public const KEY_ID = 'admin_id';
    public const KEY_VERSION = 'admin_session_version';
    public const KEY_LAST_SEEN = 'admin_last_seen';
    public const KEY_INTENDED = 'admin_intended';

    public function __construct(
        private readonly AuthServiceInterface $auth,
        private readonly Csrf $csrf,
    ) {
    }

    public function login(Request $request, AdminUser $admin): void
    {
        $session = $this->session($request);
        // New session id (prevents fixation) and a new CSRF secret.
        $session->regenerate();
        $session->set(self::KEY_ID, $admin->id);
        $session->set(self::KEY_VERSION, $admin->sessionVersion);
        $session->set(self::KEY_LAST_SEEN, time());
        CsrfCookie::rotate($this->csrf, $request);
        $request->setAttribute('admin', $admin);
    }

    public function logout(Request $request): void
    {
        $this->session($request)->invalidate();
        CsrfCookie::rotate($this->csrf, $request);
        $request->setAttribute('admin', null);
    }

    /** Keeps the current session valid after the session version was bumped (password change). */
    public function refresh(Request $request, AdminUser $admin): void
    {
        $session = $this->session($request);
        $session->regenerate();
        $session->set(self::KEY_VERSION, $admin->sessionVersion);
        $request->setAttribute('admin', $admin);
    }

    public function user(Request $request): ?AdminUser
    {
        $cached = $request->attribute('admin');
        if ($cached instanceof AdminUser) {
            return $cached;
        }

        $session = $this->session($request);
        $id = $session->get(self::KEY_ID);
        $version = $session->get(self::KEY_VERSION);
        if (!is_int($id) || !is_int($version)) {
            return null;
        }

        $admin = $this->auth->resolveSession($id, $version);
        if ($admin === null) {
            // Revoked ("sign out everywhere") or deleted: drop the stale login.
            $session->remove(self::KEY_ID);
            $session->remove(self::KEY_VERSION);

            return null;
        }
        $request->setAttribute('admin', $admin);

        return $admin;
    }

    public function isSignedIn(Request $request): bool
    {
        return is_int($this->session($request)->get(self::KEY_ID));
    }

    private function session(Request $request): Session
    {
        return $request->attribute('session');
    }
}
