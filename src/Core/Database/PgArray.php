<?php

declare(strict_types=1);

namespace App\Core\Database;

/**
 * Converts between PHP string lists and PostgreSQL text[] literals.
 * Values are always passed as bound parameters; this only handles the array syntax.
 */
final class PgArray
{
    /** @param list<string> $values */
    public static function fromList(array $values): string
    {
        $quoted = array_map(
            static fn (string $v): string => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $v) . '"',
            $values,
        );

        return '{' . implode(',', $quoted) . '}';
    }

    /** @return list<string> */
    public static function toList(?string $literal): array
    {
        if ($literal === null || $literal === '{}' || $literal === '') {
            return [];
        }

        $inner = substr($literal, 1, -1);
        $values = [];
        $current = '';
        $inQuotes = false;
        $wasQuoted = false;
        $length = strlen($inner);

        for ($i = 0; $i < $length; $i++) {
            $char = $inner[$i];
            if ($inQuotes) {
                if ($char === '\\' && $i + 1 < $length) {
                    $current .= $inner[++$i];
                } elseif ($char === '"') {
                    $inQuotes = false;
                } else {
                    $current .= $char;
                }
                continue;
            }
            if ($char === '"') {
                $inQuotes = true;
                $wasQuoted = true;
            } elseif ($char === ',') {
                $values[] = $current;
                $current = '';
                $wasQuoted = false;
            } else {
                $current .= $char;
            }
        }
        if ($current !== '' || $wasQuoted) {
            $values[] = $current;
        }

        return $values;
    }
}
