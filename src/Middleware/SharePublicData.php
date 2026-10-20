<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\View;
use App\Services\Contracts\SocialProfileServiceInterface;
use App\Services\FormTimer;

/** Data only the public pages need: footer social links and the subscribe form. Not run for admin pages. */
final class SharePublicData implements MiddlewareInterface
{
    public function __construct(
        private readonly View $view,
        private readonly SocialProfileServiceInterface $profiles,
        private readonly FormTimer $timer,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $this->view->share('socialProfiles', $this->profiles->visible());
        $this->view->share('subscribeForm', true);
        $this->view->share('subscribeTimer', $this->timer->issue());

        return $next($request);
    }
}
