<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\AnalyticsDimension;
use App\Domain\AnalyticsMetrics;
use App\Domain\AnalyticsRow;
use App\Domain\ApplyChannel;
use App\Domain\DateRange;
use App\Domain\PageType;
use App\Repositories\Contracts\AnalyticsRepositoryInterface;
use App\Repositories\Contracts\SubscriberRepositoryInterface;
use App\Services\Contracts\AnalyticsReportServiceInterface;
use App\Services\Contracts\AnalyticsRollupInterface;
use DateTimeImmutable;
use InvalidArgumentException;

final class AnalyticsReportService implements AnalyticsReportServiceInterface
{
    /** table name => [dimension, sort column, only rows with zero-result searches] */
    private const DEFINITIONS = [
        'jobs' => [AnalyticsDimension::Job, 'pageviews', false],
        'categories' => [AnalyticsDimension::Category, 'pageviews', false],
        'pages' => [AnalyticsDimension::Path, 'pageviews', false],
        'page_types' => [AnalyticsDimension::PageType, 'pageviews', false],
        'referrers' => [AnalyticsDimension::Referrer, 'pageviews', false],
        'sources' => [AnalyticsDimension::UtmSource, 'pageviews', false],
        'campaigns' => [AnalyticsDimension::UtmCampaign, 'pageviews', false],
        'devices' => [AnalyticsDimension::Device, 'pageviews', false],
        'searches' => [AnalyticsDimension::Search, 'searches', false],
        'zero_searches' => [AnalyticsDimension::Search, 'zero_results', true],
        'filters' => [AnalyticsDimension::Filter, 'searches', false],
        'channels' => [AnalyticsDimension::Channel, 'applies', false],
    ];

    private const FILTER_LABELS = [
        'location' => 'Location', 'category' => 'Category', 'type' => 'Job type', 'experience' => 'Experience level',
        'posted' => 'Date posted', 'remote' => 'Remote only', 'salary' => 'Salary',
    ];

    public function __construct(
        private readonly AnalyticsRepositoryInterface $repository,
        private readonly AnalyticsRollupInterface $rollup,
        private readonly SubscriberRepositoryInterface $subscribers,
    ) {
    }

    public function overview(DateRange $range, int $rowsPerTable = 10): array
    {
        $this->rollup->catchUp();
        $previous = $range->previous();

        $tables = [];
        foreach (array_keys(self::DEFINITIONS) as $name) {
            $tables[$name] = $this->rows($name, $range, $rowsPerTable);
        }

        return [
            ...$this->headline($range),
            'series' => $this->dailySeries($range),
            'subscribers' => $this->subscriberActivity($range),
            'previousSubscribers' => $this->subscriberActivity($previous),
            'tables' => $tables,
        ];
    }

    public function summary(DateRange $range): array
    {
        $this->rollup->catchUp();

        return $this->headline($range);
    }

    public function forJob(int $jobId, DateRange $range): array
    {
        $this->rollup->catchUp();

        return [
            'totals' => AnalyticsMetrics::fromRow($this->repository->totals($range, AnalyticsDimension::Job, (string) $jobId)),
            'series' => $this->dailySeries($range, AnalyticsDimension::Job, (string) $jobId),
        ];
    }

    public function table(string $table, DateRange $range, ?int $limit = null): array
    {
        $this->rollup->catchUp();

        return $this->rows($table, $range, $limit);
    }

    public function series(DateRange $range): array
    {
        $this->rollup->catchUp();

        return $this->dailySeries($range);
    }

    /** @return array{totals: AnalyticsMetrics, previous: AnalyticsMetrics, jobViews: int, previousJobViews: int} */
    private function headline(DateRange $range): array
    {
        $previous = $range->previous();
        $jobViews = fn (DateRange $r): int => (int) ($this->repository->totals($r, AnalyticsDimension::Job, null)['pageviews'] ?? 0);

        return [
            'totals' => AnalyticsMetrics::fromRow($this->repository->totals($range)),
            'previous' => AnalyticsMetrics::fromRow($this->repository->totals($previous)),
            'jobViews' => $jobViews($range),
            'previousJobViews' => $jobViews($previous),
        ];
    }

    /** @return list<array{day: DateTimeImmutable, metrics: AnalyticsMetrics}> */
    private function dailySeries(DateRange $range, AnalyticsDimension $dimension = AnalyticsDimension::Total, string $key = ''): array
    {
        $byDay = $this->repository->daily($range, $dimension, $key);

        return array_map(
            static fn (DateTimeImmutable $day): array => [
                'day' => $day,
                'metrics' => AnalyticsMetrics::fromRow($byDay[$day->format('Y-m-d')] ?? []),
            ],
            $range->eachDay(),
        );
    }

    /** @return array{signups: int, confirmations: int, unsubscribes: int} */
    private function subscriberActivity(DateRange $range): array
    {
        return $this->subscribers->activityBetween($range->from, $range->to->modify('+1 day'));
    }

    /** @return list<AnalyticsRow> */
    private function rows(string $table, DateRange $range, ?int $limit): array
    {
        if (!isset(self::DEFINITIONS[$table])) {
            throw new InvalidArgumentException("Unknown analytics table: {$table}");
        }
        [$dimension, $sort, $onlyZero] = self::DEFINITIONS[$table];

        return array_map(
            fn (array $row): AnalyticsRow => $this->row($dimension, $row),
            $this->repository->top($dimension, $range, $sort, $limit, $onlyZero),
        );
    }

    /** @param array<string, mixed> $row */
    private function row(AnalyticsDimension $dimension, array $row): AnalyticsRow
    {
        $key = (string) $row['key'];
        $metrics = AnalyticsMetrics::fromRow($row);

        return match ($dimension) {
            AnalyticsDimension::Job => new AnalyticsRow(
                $key,
                is_string($row['title'] ?? null) ? $row['title'] : "Job #{$key}",
                $metrics,
                is_string($row['title'] ?? null) && empty($row['deleted']) ? "/admin/jobs/{$key}" : null,
                match (true) {
                    !is_string($row['title'] ?? null) || !empty($row['deleted']) => 'deleted',
                    default => (string) $row['status'],
                },
            ),
            AnalyticsDimension::Category => new AnalyticsRow(
                $key,
                is_string($row['name'] ?? null) ? $row['name'] : 'Deleted category',
                $metrics,
                is_string($row['slug'] ?? null) ? '/categories/' . $row['slug'] : null,
            ),
            AnalyticsDimension::Path => new AnalyticsRow($key, $key, $metrics, $key),
            AnalyticsDimension::PageType => new AnalyticsRow($key, PageType::tryFrom($key)?->label() ?? $key, $metrics),
            AnalyticsDimension::Device => new AnalyticsRow($key, ucfirst($key), $metrics),
            AnalyticsDimension::Filter => new AnalyticsRow($key, self::FILTER_LABELS[$key] ?? $key, $metrics),
            AnalyticsDimension::Channel => new AnalyticsRow($key, ApplyChannel::tryFrom($key)?->label() ?? $key, $metrics),
            default => new AnalyticsRow($key, $key, $metrics),
        };
    }
}
