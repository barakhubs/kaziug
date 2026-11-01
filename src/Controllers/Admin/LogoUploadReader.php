<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Services\ImageInspector;

/** Reads an uploaded logo file into memory, turning upload failures into form errors. */
final class LogoUploadReader
{
    /**
     * @param array<string, mixed>|null $upload one $_FILES entry
     * @param array<string, string> $errors receives an error under "logo" when the upload failed
     * @return string|null the file's bytes, or null when no file was sent or it failed
     */
    public function read(?array $upload, array &$errors): ?string
    {
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $size = (int) ($upload['size'] ?? 0);
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE || $size > ImageInspector::MAX_BYTES) {
            $errors['logo'] = 'The logo must be 512 KB or smaller.';

            return null;
        }
        $path = (string) ($upload['tmp_name'] ?? '');
        if ($error !== UPLOAD_ERR_OK || $path === '' || !is_file($path)) {
            $errors['logo'] = 'The upload failed. Please try again.';

            return null;
        }

        return (string) file_get_contents($path, false, null, 0, ImageInspector::MAX_BYTES + 1);
    }
}
