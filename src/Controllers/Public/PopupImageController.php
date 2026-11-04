<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Domain\NotFoundException;
use App\Services\Contracts\PopupReaderInterface;
use App\Services\Contracts\PopupServiceInterface;

/**
 * Popup images from the database: /media/popups/{id}/image only for the popup currently showing,
 * and /admin/popups/{id}/image (behind the admin login) for any popup.
 */
final class PopupImageController
{
    public function __construct(
        private readonly PopupReaderInterface $reader,
        private readonly PopupServiceInterface $popups,
    ) {
    }

    public function show(Request $request): Response
    {
        $image = $this->reader->currentImage((int) $request->attribute('id')) ?? throw new NotFoundException('No image.');

        return $this->respond($image, 'public');
    }

    public function showForAdmin(Request $request): Response
    {
        $image = $this->popups->image((int) $request->attribute('id')) ?? throw new NotFoundException('No image.');

        return $this->respond($image, 'private');
    }

    /** @param array{mime_type: string, content: string} $image */
    private function respond(array $image, string $cache): Response
    {
        // The URL carries ?v=<version>, so the file can be cached for a long time.
        return new Response($image['content'], 200, [
            'Content-Type' => $image['mime_type'],
            'Content-Length' => (string) strlen($image['content']),
            'Cache-Control' => "{$cache}, max-age=31536000, immutable",
            'Content-Disposition' => 'inline; filename="popup"',
            'Content-Security-Policy' => "default-src 'none'",
        ]);
    }
}
