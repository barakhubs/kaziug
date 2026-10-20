<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\View;
use App\Domain\Category;
use App\Services\Contracts\CategoryServiceInterface;
use App\Services\Contracts\JobSearchServiceInterface;
use App\Services\Contracts\SocialProfileServiceInterface;
use App\Services\FormTimer;

/**
 * Data only the public pages need: footer social links, the subscribe form, and the
 * "Browse jobs" links (cities and categories) that help visitors and crawlers reach every listing.
 */
final class SharePublicData implements MiddlewareInterface
{
    private const FOOTER_LINKS = 8;

    public function __construct(
        private readonly View $view,
        private readonly SocialProfileServiceInterface $profiles,
        private readonly FormTimer $timer,
        private readonly JobSearchServiceInterface $jobs,
        private readonly CategoryServiceInterface $categories,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $this->view->share('socialProfiles', $this->profiles->visible());
        $this->view->share('subscribeForm', true);
        $this->view->share('subscribeTimer', $this->timer->issue());
        $this->view->share('footerCities', array_slice($this->jobs->cities(), 0, self::FOOTER_LINKS));
        $categories = array_values(array_filter($this->categories->active(), static fn (Category $c): bool => $c->jobCount > 0));
        usort($categories, static fn (Category $a, Category $b): int => $b->jobCount <=> $a->jobCount);
        $this->view->share('footerCategories', array_slice($categories, 0, self::FOOTER_LINKS));

        return $next($request);
    }
}
