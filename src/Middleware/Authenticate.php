<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\Session\Session;
use App\Core\View;
use App\Services\AdminGuard;

/**
 * Lets only the signed-in admin through. Everyone else is sent to the login page,
 * which returns them to the page they asked for after signing in.
 */
final class Authenticate implements MiddlewareInterface
{
    public function __construct(
        private readonly AdminGuard $guard,
        private readonly View $view,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $admin = $this->guard->user($request);
        if ($admin === null) {
            if ($request->isMethod('GET')) {
                /** @var Session $session */
                $session = $request->attribute('session');
                $session->set(AdminGuard::KEY_INTENDED, $request->fullUrlPath());
            }

            return Response::redirect('/admin/login');
        }

        $this->view->share('admin', $admin);

        return $next($request);
    }
}
