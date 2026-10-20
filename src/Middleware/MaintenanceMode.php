<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Http\MiddlewareInterface;
use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Services\AdminGuard;
use App\Services\Contracts\SettingsReaderInterface;

/**
 * When maintenance mode is on, visitors get a 503 page. The back office, /health and the logo
 * keep working, and a signed-in admin can still browse the public site to check it.
 */
final class MaintenanceMode implements MiddlewareInterface
{
    private const ALWAYS_OPEN = ['/admin', '/health', '/media'];

    public function __construct(
        private readonly SettingsReaderInterface $settings,
        private readonly AdminGuard $guard,
        private readonly Responder $responder,
    ) {
    }

    public function process(Request $request, callable $next): Response
    {
        if (!$this->settings->site()->maintenanceMode || $this->isOpenPath($request->path) || $this->guard->isSignedIn($request)) {
            return $next($request);
        }

        return $this->responder->view('public/maintenance', [], 503)
            ->setHeader('Retry-After', '3600')
            ->setHeader('Cache-Control', 'no-store');
    }

    private function isOpenPath(string $path): bool
    {
        foreach (self::ALWAYS_OPEN as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
