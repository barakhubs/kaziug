<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\View;
use App\Services\Contracts\SocialProfileServiceInterface;

/** Data only the public pages need (footer social links). Not run for admin pages. */
final class SharePublicData implements MiddlewareInterface
{
    public function __construct(
        private readonly View $view,
        private readonly SocialProfileServiceInterface $profiles,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $this->view->share('socialProfiles', $this->profiles->visible());

        return $next($request);
    }
}
