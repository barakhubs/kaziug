<?php

declare(strict_types=1);

namespace App\Services;

/** Plain-text alternative for sanitised newsletter HTML. */
final class HtmlToText
{
    public function convert(string $html): string
    {
        $text = preg_replace_callback(
            '#<a\s[^>]*href="([^"]*)"[^>]*>(.*?)</a>#is',
            static function (array $m): string {
                $label = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $url = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

                return $label === '' || $label === $url ? $url : "{$label} ({$url})";
            },
            $html,
        ) ?? $html;

        $text = preg_replace('#<li[^>]*>#i', "\n- ", $text) ?? $text;
        $text = preg_replace('#<hr[^>]*>#i', "\n----\n", $text) ?? $text;
        $text = preg_replace('#<br\s*/?>#i', "\n", $text) ?? $text;
        $text = preg_replace('#</(p|h2|h3|ul|ol|blockquote)>#i', "\n\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/ *\n */", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
