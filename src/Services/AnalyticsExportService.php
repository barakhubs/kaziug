<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Actor;
use App\Domain\DateRange;
use App\Services\Contracts\AnalyticsExportServiceInterface;
use App\Services\Contracts\AnalyticsReportServiceInterface;
use App\Services\Contracts\AuditServiceInterface;

final class AnalyticsExportService implements AnalyticsExportServiceInterface
{
    private const DAILY = 'daily';
    private const METRICS = ['page_views', 'visitors', 'apply_clicks', 'searches', 'zero_result_searches'];

    public function __construct(
        private readonly AnalyticsReportServiceInterface $reports,
        private readonly AuditServiceInterface $audit,
    ) {
    }

    /** @return list<string> */
    public function tables(): array
    {
        return [self::DAILY, ...AnalyticsReportServiceInterface::TABLES];
    }

    public function export(string $table, DateRange $range, $handle, Actor $actor): int
    {
        $count = $table === self::DAILY ? $this->daily($range, $handle) : $this->breakdown($table, $range, $handle);
        $this->audit->record($actor, 'analytics.exported', 'analytics', null, [
            'table' => $table,
            'from' => $range->from->format('Y-m-d'),
            'to' => $range->to->format('Y-m-d'),
            'rows' => $count,
        ]);

        return $count;
    }

    /** @param resource $handle */
    private function daily(DateRange $range, $handle): int
    {
        CsvWriter::row($handle, ['date', ...self::METRICS]);
        $count = 0;
        foreach ($this->reports->series($range) as $point) {
            $m = $point['metrics'];
            CsvWriter::row($handle, [$point['day']->format('Y-m-d'), ...$this->cells($m->pageviews, $m->visitors, $m->applies, $m->searches, $m->zeroResults)]);
            $count++;
        }

        return $count;
    }

    /** @param resource $handle */
    private function breakdown(string $table, DateRange $range, $handle): int
    {
        CsvWriter::row($handle, ['key', 'label', ...self::METRICS]);
        $count = 0;
        foreach ($this->reports->table($table, $range) as $row) {
            $m = $row->metrics;
            CsvWriter::row($handle, [$row->key, $row->label, ...$this->cells($m->pageviews, $m->visitors, $m->applies, $m->searches, $m->zeroResults)]);
            $count++;
        }

        return $count;
    }

    /** @return list<string> */
    private function cells(int ...$values): array
    {
        return array_map('strval', $values);
    }
}
