<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Inline SVG icons from views/icons (Font Awesome Free 6.5.2, CC BY 4.0; see views/icons/LICENSE.txt).
 * Inline SVG replaces the Font Awesome web fonts: a few hundred bytes per icon instead of
 * about 370 KB of CSS and fonts on every page.
 */
final class Icon
{
    private const DIR = __DIR__ . '/../../views/icons/';

    /** @var array<string, string> rendered markup by icon name */
    private static array $cache = [];

    /**
     * @param string $name e.g. "brands/whatsapp" or "solid/sliders"
     * @return string decorative <svg> markup, 1em tall, coloured with currentColor
     */
    public static function svg(string $name): string
    {
        return self::$cache[$name] ??= self::render($name);
    }

    private static function render(string $name): string
    {
        if (preg_match('#^(brands|solid)/[a-z0-9-]+$#', $name) !== 1) {
            throw new RuntimeException("Unknown icon {$name}.");
        }
        $source = @file_get_contents(self::DIR . $name . '.svg');
        if ($source === false
            || preg_match('/viewBox="0 0 (\d+) (\d+)"/', $source, $box) !== 1
            || preg_match('/<path d="([^"]+)"/', $source, $path) !== 1) {
            throw new RuntimeException("Unknown icon {$name}.");
        }
        // Width in em keeps each glyph's own proportions at a height of 1em.
        $width = round((int) $box[1] / (int) $box[2], 3);

        return '<svg class="icon" width="' . $width . 'em" height="1em" viewBox="0 0 ' . $box[1] . ' ' . $box[2] . '"'
            . ' aria-hidden="true" focusable="false"><path fill="currentColor" d="' . $path[1] . '"/></svg>';
    }
}
