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
    /** Blog covers are photos shown wide, so they may be larger than logos. */
    public const MAX_COVER_BYTES = 1024 * 1024;
    public const MAX_DIMENSION = 2000;
    private const ALLOWED = ['image/png', 'image/jpeg', 'image/webp'];

    /** @return string the detected MIME type */
    public function assertAcceptableLogo(string $bytes): string
    {
        return $this->assertAcceptable($bytes, 'logo', 'The logo', self::MAX_BYTES);
    }

    /** @return string the detected MIME type */
    public function assertAcceptableCover(string $bytes): string
    {
        return $this->assertAcceptable($bytes, 'cover', 'The cover image', self::MAX_COVER_BYTES);
    }

    /** @return string the detected MIME type */
    public function assertAcceptablePopupImage(string $bytes): string
    {
        return $this->assertAcceptable($bytes, 'image', 'The image', self::MAX_COVER_BYTES);
    }

    /**
     * @param string $field the form field errors belong to
     * @param string $subject how messages name the image, e.g. "The logo"
     * @return string the detected MIME type
     */
    private function assertAcceptable(string $bytes, string $field, string $subject, int $maxBytes): string
    {
        if ($bytes === '') {
            throw new DomainException('Choose an image file.', $field);
        }
        if (strlen($bytes) > $maxBytes) {
            throw new DomainException("{$subject} must be " . intdiv($maxBytes, 1024) . ' KB or smaller.', $field);
        }

        $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
        if (!in_array($mime, self::ALLOWED, true)) {
            throw new DomainException("{$subject} must be a PNG, JPEG or WebP image.", $field);
        }

        $size = @getimagesizefromstring($bytes);
        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            throw new DomainException('That file is not a readable image.', $field);
        }
        if ($size[0] > self::MAX_DIMENSION || $size[1] > self::MAX_DIMENSION) {
            throw new DomainException("{$subject} must be at most " . self::MAX_DIMENSION . ' pixels wide and tall.', $field);
        }

        return $mime;
    }
}
