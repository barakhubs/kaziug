<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Domain\PageContext;
use App\Services\Contracts\AnalyticsRecorderInterface;

/**
 * Counts a page view after a public page rendered successfully. The controller may describe the
 * page (job, category, search) with a PageContext request attribute; otherwise it counts as "other".
 * Only attached to routes whose URLs hold no secrets (not the subscription token pages).
 */
final class RecordPageView implements MiddlewareInterface
{
    public function __construct(private readonly AnalyticsRecorderInterface $recorder)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('GET') && $response->status() === 200
            && str_starts_with((string) $response->header('Content-Type'), 'text/html')) {
            $page = $request->attribute(PageContext::ATTRIBUTE);
            $this->recorder->recordPageView($request, $page instanceof PageContext ? $page : new PageContext());
        }

        return $response;
    }
}
