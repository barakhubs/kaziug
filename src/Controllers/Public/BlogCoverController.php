<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Domain\NotFoundException;
use App\Services\Contracts\BlogServiceInterface;

/**
 * Serves blog cover images from the database: /media/blog/{id}/cover for live posts only, and
 * /admin/blog/{id}/cover (behind the admin login) for drafts and scheduled posts too.
 */
final class BlogCoverController
{
    public function __construct(private readonly BlogServiceInterface $blog)
    {
    }

    public function show(Request $request): Response
    {
        return $this->respond((int) $request->attribute('id'), true);
    }

    public function showForAdmin(Request $request): Response
    {
        return $this->respond((int) $request->attribute('id'), false);
    }

    private function respond(int $id, bool $liveOnly): Response
    {
        $cover = $this->blog->cover($id, $liveOnly) ?? throw new NotFoundException('No cover image.');

        // The URL carries ?v=<version>, so the file can be cached for a long time (privately in the back office).
        return new Response($cover['content'], 200, [
            'Content-Type' => $cover['mime_type'],
            'Content-Length' => (string) strlen($cover['content']),
            'Cache-Control' => $liveOnly ? 'public, max-age=31536000, immutable' : 'private, max-age=31536000, immutable',
            'Content-Disposition' => 'inline; filename="cover"',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
