<?php

declare(strict_types=1);

namespace App\Services;

/** Writes CSV rows safely for spreadsheet apps. */
final class CsvWriter
{
    /**
     * Cells starting with = + - @ (or tab/CR) are prefixed with ' so spreadsheet apps don't
     * run them as formulas (CSV injection).
     */
    public static function cell(?string $value): string
    {
        $value ??= '';

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'" . $value : $value;
    }

    /**
     * @param resource $handle
     * @param list<string|null> $cells
     */
    public static function row($handle, array $cells): void
    {
        fputcsv($handle, array_map(self::cell(...), $cells), ',', '"', '');
    }
}
