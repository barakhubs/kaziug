<?php

declare(strict_types=1);

/*
 * The one output-escaping helper used by every template. Escapes for HTML text and
 * quoted attribute contexts. URLs placed in href/src must also be validated on input
 * (only http/https/mailto are ever stored).
 */
if (!function_exists('e')) {
    function e(mixed $value): string
    {
        if ($value === null || $value === false) {
            return '';
        }
        if (is_bool($value)) {
            return '1';
        }
        if (is_array($value) || (is_object($value) && !method_exists($value, '__toString'))) {
            // Re-displayed form input can be an array if someone posts field[]=...; show nothing.
            return '';
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
