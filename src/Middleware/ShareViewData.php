<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\View;

/**
 * Makes request-wide values (CSRF token, session flash, current path) available to every template.
 */
final class ShareViewData implements MiddlewareInterface
{
    public function __construct(private readonly View $view)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $this->view->share('csrfToken', (string) $request->attribute('csrf_token', ''));
        $this->view->share('session', $request->attribute('session'));
        $this->view->share('currentPath', $request->path);

        return $next($request);
    }
}
