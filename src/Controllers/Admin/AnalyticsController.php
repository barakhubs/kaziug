<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Http\Request;
use App\Core\Http\Responder;
use App\Core\Http\Response;
use App\Domain\DateRange;
use App\Domain\NotFoundException;
use App\Services\Contracts\AnalyticsExportServiceInterface;
use App\Services\Contracts\AnalyticsReportServiceInterface;

final class AnalyticsController
{
    use AdminContext;

    public function __construct(
        private readonly AnalyticsReportServiceInterface $reports,
        private readonly AnalyticsExportServiceInterface $exports,
        private readonly AnalyticsPresenter $presenter,
        private readonly Responder $responder,
    ) {
    }

    public function index(Request $request): Response
    {
        $range = DateRange::fromQuery($request->queryAll());
        $report = $this->reports->overview($range);

        return $this->responder->view('admin/analytics/index', [
            'range' => $range,
            'report' => $report,
            'tiles' => $this->presenter->tiles($report, $report['subscribers'], $report['previousSubscribers']),
            'visitorsChart' => $this->presenter->chart('Visitors per day', $report['series'], 'visitors'),
            'appliesChart' => $this->presenter->chart('Apply clicks per day', $report['series'], 'applies', 180),
        ]);
    }

    public function export(Request $request): Response
    {
        $table = $request->query('table');
        if (!is_string($table) || !in_array($table, $this->exports->tables(), true)) {
            throw new NotFoundException('Unknown analytics table.');
        }
        $range = DateRange::fromQuery($request->queryAll());
        $actor = $this->actor($request);
        $filename = sprintf('analytics-%s-%s-to-%s.csv', $table, $range->from->format('Y-m-d'), $range->to->format('Y-m-d'));

        return new Response(function () use ($table, $range, $actor): void {
            $out = fopen('php://output', 'w');
            $this->exports->export($table, $range, $out, $actor);
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store',
        ]);
    }
}
