<?php

declare(strict_types=1);

namespace App\Mail;

use DOMDocument;
use DOMElement;
use DOMText;

/**
 * Adds inline styles to sanitised newsletter HTML. Gmail and Outlook ignore <style> blocks, so
 * the look has to travel on each tag. Runs after the sanitiser (which strips every style
 * attribute), so the only styles in a sent email are the fixed ones below.
 * A paragraph whose only content is one link is shown as a button.
 */
final class EmailStyler
{
    private const STYLES = [
        'h2' => 'margin:0 0 12px;font-size:22px;line-height:1.3;color:#1c2430;',
        'h3' => 'margin:18px 0 4px;font-size:18px;line-height:1.35;color:#1c2430;',
        'p' => 'margin:0 0 12px;',
        'ul' => 'margin:0 0 12px;padding-left:22px;',
        'ol' => 'margin:0 0 12px;padding-left:22px;',
        'li' => 'margin:0 0 4px;',
        'a' => 'color:#0c6b58;font-weight:600;',
        'blockquote' => 'margin:0 0 12px;padding:8px 14px;border-left:4px solid #0c6b58;background:#eef3f1;',
        'hr' => 'border:0;border-top:1px solid #dfe3ea;margin:18px 0;',
    ];

    private const HEADING_LINK = 'color:#084f41;text-decoration:none;';
    private const BUTTON = 'display:inline-block;background:#f2b233;color:#1c2430;font-weight:700;text-decoration:none;padding:11px 20px;border-radius:8px;';

    public function style(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_NONET | LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $root = $doc->getElementById('__root');
        if ($root === null) {
            return $html;
        }

        foreach (iterator_to_array($root->getElementsByTagName('*')) as $element) {
            $tag = strtolower($element->tagName);
            if (isset(self::STYLES[$tag])) {
                $element->setAttribute('style', self::STYLES[$tag]);
            }
        }
        foreach (iterator_to_array($root->getElementsByTagName('a')) as $link) {
            $parent = $link->parentNode;
            if ($parent instanceof DOMElement && in_array(strtolower($parent->tagName), ['h2', 'h3'], true)) {
                $link->setAttribute('style', self::HEADING_LINK);
            } elseif ($parent instanceof DOMElement && strtolower($parent->tagName) === 'p' && $this->isOnlyContent($link, $parent)) {
                $link->setAttribute('style', self::BUTTON);
                $parent->setAttribute('style', 'margin:18px 0;');
            }
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    private function isOnlyContent(DOMElement $link, DOMElement $parent): bool
    {
        foreach ($parent->childNodes as $child) {
            if ($child !== $link && !($child instanceof DOMText && trim($child->textContent) === '')) {
                return false;
            }
        }

        return true;
    }
}
