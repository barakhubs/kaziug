<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Domain\DomainException;
use App\Services\AdminGuard;
use App\Services\Contracts\AdminAccountServiceInterface;
use App\Validation\Validator;

final class AccountController
{
    use AdminContext;

    public function __construct(
        private readonly AdminAccountServiceInterface $accounts,
        private readonly AdminGuard $guard,
        private readonly Validator $validator,
        private readonly Responder $responder,
    ) {
    }

    public function show(Request $request): Response
    {
        return $this->responder->view('admin/account/show', ['errors' => []]);
    }

    public function updatePassword(Request $request): Response
    {
        $input = $this->validator->validate($request->all(), [
            'current_password' => 'required|max:200',
            'password' => 'required|max:200',
            'password_confirmation' => 'required|same:password',
        ]);
        if ($input->fails()) {
            return $this->responder->view('admin/account/show', ['errors' => $input->errors], 422);
        }

        try {
            $admin = $this->accounts->changePassword(
                $this->admin($request),
                (string) $request->input('current_password'),
                (string) $request->input('password'),
                $this->actor($request),
            );
        } catch (DomainException $e) {
            return $this->responder->view('admin/account/show', ['errors' => [$e->field ?? 'password' => $e->getMessage()]], 422);
        }

        $this->guard->refresh($request, $admin);

        return $this->responder->redirectWith($request, '/admin/account', 'success', 'Password changed. Other sessions have been signed out.');
    }

    public function logoutEverywhere(Request $request): Response
    {
        $this->accounts->signOutEverywhere($this->admin($request), $this->actor($request));
        $this->guard->logout($request);

        return $this->responder->redirectWith($request, '/admin/login', 'success', 'Signed out of all sessions, including this one.');
    }
}
