<?php

declare(strict_types=1);

namespace App\Domain;

final class Slug
{
    public static function from(string $text, int $maxLength = 80): string
    {
        $ascii = function_exists('iconv') ? (string) @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) : $text;
        $slug = strtolower((string) preg_replace('/[^A-Za-z0-9]+/', '-', $ascii));
        $slug = trim(substr(trim($slug, '-'), 0, $maxLength), '-');

        return $slug !== '' ? $slug : 'item';
    }

    /** "slug", "slug-2", "slug-3" ... until $exists says it's free. */
    public static function unique(string $base, callable $exists): string
    {
        $candidate = $base;
        for ($n = 2; $exists($candidate); $n++) {
            $candidate = $base . '-' . $n;
        }

        return $candidate;
    }
}
