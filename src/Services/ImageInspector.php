<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\DomainException;
use finfo;

/**
 * Accepts only real PNG, JPEG or WebP images, detected from the file's bytes. SVG is refused
 * on purpose: it can carry scripts.
 */
final class ImageInspector
{
    public const MAX_BYTES = 512 * 1024;
    public const MAX_DIMENSION = 2000;
    private const ALLOWED = ['image/png', 'image/jpeg', 'image/webp'];

    /** @return string the detected MIME type */
    public function assertAcceptableLogo(string $bytes): string
    {
        if ($bytes === '') {
            throw new DomainException('Choose an image file.', 'logo');
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            throw new DomainException('The logo must be 512 KB or smaller.', 'logo');
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!in_array($mime, self::ALLOWED, true)) {
            throw new DomainException('The logo must be a PNG, JPEG or WebP image.', 'logo');
        }

        $size = @getimagesizefromstring($bytes);
        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            throw new DomainException('That file is not a readable image.', 'logo');
        }
        if ($size[0] > self::MAX_DIMENSION || $size[1] > self::MAX_DIMENSION) {
            throw new DomainException('The logo must be at most ' . self::MAX_DIMENSION . ' pixels wide and tall.', 'logo');
        }

        return $mime;
    }
}
