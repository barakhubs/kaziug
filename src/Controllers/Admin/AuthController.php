<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Domain\Actor;
use App\Services\AdminGuard;
use App\Services\Contracts\AuditServiceInterface;
use App\Services\Contracts\AuthServiceInterface;
use App\Validation\Validator;

final class AuthController
{
    public function __construct(
        private readonly AuthServiceInterface $auth,
        private readonly AdminGuard $guard,
        private readonly AuditServiceInterface $audit,
        private readonly Validator $validator,
        private readonly Responder $responder,
    ) {
    }

    public function showLogin(Request $request): Response
    {
        if ($this->guard->user($request) !== null) {
            return $this->responder->redirect('/admin');
        }

        return $this->responder->view('admin/auth/login', ['email' => '', 'error' => null]);
    }

    public function login(Request $request): Response
    {
        $input = $this->validator->validate($request->all(), ['email' => 'required|email', 'password' => 'required|max:200']);
        $email = (string) ($request->string('email'));
        if ($input->fails()) {
            return $this->loginForm($email, 'Enter your email and password.', 422);
        }

        $result = $this->auth->attempt($email, (string) $request->input('password'), $request->ip());
        if ($result->isLocked()) {
            $minutes = (int) ceil($result->lockedSeconds / 60);

            return $this->loginForm($email, "Too many failed attempts. Try again in {$minutes} minute(s).", 429);
        }
        if (!$result->succeeded() || $result->admin === null) {
            return $this->loginForm($email, 'Incorrect email or password.', 422);
        }

        /** @var Session $session */
        $session = $request->attribute('session');
        $intended = $session->get(AdminGuard::KEY_INTENDED);
        $session->remove(AdminGuard::KEY_INTENDED);

        $this->guard->login($request, $result->admin);
        $this->audit->record(Actor::admin($result->admin, $request->ip()), 'auth.login', 'admin_user', $result->admin->id);

        return $this->responder->redirect($this->safeIntended($intended));
    }

    public function logout(Request $request): Response
    {
        $admin = $this->guard->user($request);
        if ($admin !== null) {
            $this->audit->record(Actor::admin($admin, $request->ip()), 'auth.logout', 'admin_user', $admin->id);
        }
        $this->guard->logout($request);

        return $this->responder->redirectWith($request, '/admin/login', 'success', 'You have signed out.');
    }

    private function loginForm(string $email, string $error, int $status): Response
    {
        return $this->responder->view('admin/auth/login', ['email' => $email, 'error' => $error], $status);
    }

    /** Only same-site admin paths, so the login form can't be used as an open redirect. */
    private function safeIntended(mixed $intended): string
    {
        return is_string($intended) && preg_match('#^/admin(/[^/\\\\]|$|\?)#', $intended) === 1 ? $intended : '/admin';
    }
}
