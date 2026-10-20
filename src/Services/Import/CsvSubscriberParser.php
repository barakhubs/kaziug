<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * Parses an uploaded subscriber CSV: one row per subscriber, columns "email" and optionally
 * "name" (a header row is detected and skipped). Validates every row and reports problems by
 * line number instead of failing the whole file.
 */
final class CsvSubscriberParser
{
    public const MAX_ROWS = 5000;

    /**
     * @return array{rows: list<array{email: string, name: ?string}>, invalid: list<array{line: int, reason: string}>, duplicates_in_file: int}
     */
    public function parse(string $content): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $rows = [];
        $invalid = [];
        $seen = [];
        $duplicates = 0;
        $line = 0;
        $emailColumn = 0;
        $nameColumn = 1;

        while (($fields = fgetcsv($handle, 0, $this->delimiter($content), '"', '')) !== false) {
            $line++;
            if ($fields === [null] || implode('', array_map('strval', $fields)) === '') {
                continue;
            }
            $fields = array_map(static fn (?string $f): string => trim((string) $f), $fields);

            if ($line === 1 && ($header = array_map('strtolower', $fields)) !== [] && in_array('email', $header, true)) {
                $emailColumn = (int) array_search('email', $header, true);
                $nameColumn = array_search('name', $header, true);

                continue;
            }
            if (count($rows) + count($invalid) >= self::MAX_ROWS) {
                $invalid[] = ['line' => $line, 'reason' => 'Too many rows: only the first ' . self::MAX_ROWS . ' are processed.'];
                break;
            }

            $email = strtolower($fields[$emailColumn] ?? '');
            $name = $nameColumn === false ? '' : ($fields[$nameColumn] ?? '');
            if ($email === '' || strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                $invalid[] = ['line' => $line, 'reason' => 'Not a valid email address.'];
                continue;
            }
            if (mb_strlen($name) > 100) {
                $invalid[] = ['line' => $line, 'reason' => 'Name is longer than 100 characters.'];
                continue;
            }
            if (isset($seen[$email])) {
                $duplicates++;
                continue;
            }
            $seen[$email] = true;
            $rows[] = ['email' => $email, 'name' => $name !== '' ? $name : null];
        }
        fclose($handle);

        return ['rows' => $rows, 'invalid' => $invalid, 'duplicates_in_file' => $duplicates];
    }

    private function delimiter(string $content): string
    {
        $firstLine = strtok($content, "\n") ?: '';

        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }
}
