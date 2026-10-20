<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Core\View;
use App\Services\Contracts\SettingsReaderInterface;

/**
 * Makes request-wide values available to every template: CSRF token, session (flash),
 * current path, public base URL and site settings.
 */
final class ShareViewData implements MiddlewareInterface
{
    public function __construct(
        private readonly View $view,
        private readonly Config $config,
        private readonly SettingsReaderInterface $settings,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        $this->view->share('csrfToken', (string) $request->attribute('csrf_token', ''));
        $this->view->share('session', $request->attribute('session'));
        $this->view->share('currentPath', $request->path);
        $this->view->share('appUrl', $this->config->string('app.url'));
        // Settings are cached per request, never across requests.
        $this->settings->forget();
        $this->view->share('site', $this->settings->site());

        return $next($request);
    }
}
