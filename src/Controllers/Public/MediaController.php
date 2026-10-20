<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Domain\NotFoundException;
use App\Services\Contracts\SettingsServiceInterface;

/** Serves the uploaded logo, which lives in the database rather than the web root. */
final class MediaController
{
    public function __construct(private readonly SettingsServiceInterface $settings)
    {
    }

    public function logo(Request $request): Response
    {
        $logo = $this->settings->logo() ?? throw new NotFoundException('No logo uploaded.');

        // The URL carries ?v=<version>, so the file can be cached for a long time.
        return new Response($logo['content'], 200, [
            'Content-Type' => $logo['mime_type'],
            'Content-Length' => (string) strlen($logo['content']),
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'Content-Disposition' => 'inline; filename="logo"',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
