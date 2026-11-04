<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\View;
use App\Services\Contracts\PopupReaderInterface;

/**
 * Hands the current marketing popup to the public layout. Attached only to the pages that may
 * show it (home and job pages), so every other page stays popup-free.
 */
final class ShareMarketingPopup implements MiddlewareInterface
{
    public function __construct(
        private readonly View $view,
        private readonly PopupReaderInterface $popups,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $this->view->share('marketingPopup', $this->popups->current());
        try {
            return $next($request);
        } finally {
            // The page is rendered by now; don't let the popup leak into a later render in the same process.
            $this->view->share('marketingPopup', null);
        }
    }
}
