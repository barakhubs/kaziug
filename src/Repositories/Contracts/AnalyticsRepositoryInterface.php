<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Domain\AnalyticsDimension;
use App\Domain\AnalyticsEvent;
use App\Domain\DateRange;
use DateTimeImmutable;

/** Raw analytics_events and their daily roll-up in analytics_daily. */
interface AnalyticsRepositoryInterface
{
    public const SORTS = ['pageviews', 'visitors', 'applies', 'searches', 'zero_results'];

    public function record(AnalyticsEvent $event): void;

    /** The most recent day already in analytics_daily, or null if nothing was rolled up yet. */
    public function latestRolledDay(): ?DateTimeImmutable;

    public function earliestEventAt(): ?DateTimeImmutable;

    /** Replaces analytics_daily rows for $day with totals of the events in [$start, $end). */
    public function rollUpDay(DateTimeImmutable $day, DateTimeImmutable $start, DateTimeImmutable $end): void;

    /** Deletes raw events older than $cutoff. Returns rows removed. */
    public function purgeEventsBefore(DateTimeImmutable $cutoff): int;

    /**
     * @param string|null $key null sums every key of the dimension (visitors are then not unique)
     * @return array<string, int> summed columns over the range
     */
    public function totals(DateRange $range, AnalyticsDimension $dimension = AnalyticsDimension::Total, ?string $key = ''): array;

    /** @return array<string, array<string, int>> 'Y-m-d' => summed columns, only days that have data */
    public function daily(DateRange $range, AnalyticsDimension $dimension = AnalyticsDimension::Total, string $key = ''): array;

    /**
     * Breakdown rows, highest $sort first. Job rows add title, slug, status, deleted (left join, so
     * deleted jobs still show); category rows add name.
     * @param string $sort one of self::SORTS
     * @param int|null $limit null for every row
     * @return list<array<string, mixed>>
     */
    public function top(AnalyticsDimension $dimension, DateRange $range, string $sort = 'pageviews', ?int $limit = 10, bool $onlyZeroResults = false): array;
}
