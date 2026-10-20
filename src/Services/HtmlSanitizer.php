<?php

declare(strict_types=1);

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Allowlist HTML sanitiser for admin-written newsletters (prevents stored XSS in the preview,
 * the back office and recipients' mail clients).
 *
 * Why an allowlist on a parsed DOM rather than regex or a blocklist: the parser normalises
 * whatever is submitted, and anything not explicitly allowed is dropped, so new attack
 * vectors (unknown tags, attributes, event handlers, encodings) are refused by default.
 *
 * - Allowed tags: p br strong em b i u h2 h3 ul ol li a blockquote hr
 * - Allowed attributes: href on <a> only, and only http(s) or mailto URLs
 * - script/style/iframe/object/embed/template/svg/math/form and their content are removed
 * - Any other tag is unwrapped: its text survives, the tag does not
 * - Comments, processing instructions and all other attributes (on*, style, class...) are removed
 */
final class HtmlSanitizer
{
    private const ALLOWED_TAGS = ['p', 'br', 'strong', 'em', 'b', 'i', 'u', 'h2', 'h3', 'ul', 'ol', 'li', 'a', 'blockquote', 'hr'];
    private const DROP_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'template', 'svg', 'math', 'form', 'noscript', 'head', 'title', 'textarea', 'select'];
    private const MAX_LENGTH = 200_000;

    public function sanitize(string $html): string
    {
        $html = mb_substr($html, 0, self::MAX_LENGTH);
        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        // The XML prolog tells libxml the input is UTF-8; LIBXML_NONET forbids network access.
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>', LIBXML_NONET | LIBXML_HTML_NODEFDTD | LIBXML_HTML_NOIMPLIED);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__root');
        if ($root === null) {
            return '';
        }
        $this->cleanChildren($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return trim($out);
    }

    private function cleanChildren(DOMNode $node): void
    {
        // Copy first: the live list changes while we modify it.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMText) {
                continue;
            }
            if (!$child instanceof DOMElement) {
                $node->removeChild($child); // comments, CDATA, processing instructions

                continue;
            }

            $tag = strtolower($child->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->removeChild($child);

                continue;
            }

            $this->cleanChildren($child);

            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                while ($child->firstChild !== null) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);

                continue;
            }

            $this->cleanAttributes($child, $tag);
        }
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        $href = $tag === 'a' ? trim($element->getAttribute('href')) : '';
        foreach (iterator_to_array($element->attributes) as $attribute) {
            $element->removeAttribute($attribute->nodeName);
        }
        if ($tag === 'a' && $this->isSafeUrl($href)) {
            $element->setAttribute('href', $href);
        }
    }

    private function isSafeUrl(string $url): bool
    {
        // Control characters and whitespace inside the scheme are how "java\tscript:" tricks work.
        if ($url === '' || preg_match('/[\x00-\x20]/', $url) === 1) {
            return false;
        }

        return preg_match('#^(https?://[^\s]+|mailto:[^\s@]+@[^\s@]+)$#i', $url) === 1;
    }
}
