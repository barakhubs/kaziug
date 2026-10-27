<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Http\Request;

/**
 * Decides whether a request counts in analytics. Bots, crawlers, link previews and monitors are
 * left out, as are visitors who send Do Not Track or Global Privacy Control, and the signed-in admin.
 */
final class TrafficFilter
{
    public function __construct(
        private readonly BotDetector $bots,
        private readonly AdminGuard $guard,
    ) {
    }

    public function shouldCount(Request $request): bool
    {
        return !$this->bots->isBot($request->userAgent())
            && !$this->optedOut($request)
            && !$this->guard->isSignedIn($request);
    }

    private function optedOut(Request $request): bool
    {
        return $request->header('dnt') === '1' || $request->header('sec-gpc') === '1';
    }
}
