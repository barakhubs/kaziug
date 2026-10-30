<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database\Database;
use App\Core\Database\DbTime;
use App\Domain\AnalyticsDimension;
use App\Domain\AnalyticsEvent;
use App\Domain\DateRange;
use App\Repositories\Contracts\AnalyticsRepositoryInterface;
use DateTimeImmutable;
use InvalidArgumentException;

final class MysqlAnalyticsRepository implements AnalyticsRepositoryInterface
{
    private const SUMS = 'CAST(sum(pageviews) AS SIGNED) AS pageviews, CAST(sum(visitors) AS SIGNED) AS visitors,
        CAST(sum(applies) AS SIGNED) AS applies, CAST(sum(searches) AS SIGNED) AS searches,
        CAST(sum(zero_results) AS SIGNED) AS zero_results';

    /**
     * How each dimension is derived from the day's events (alias e): [key expression, extra FROM, condition].
     * Fixed SQL fragments only; nothing here comes from input.
     */
    private const ROLLUP = [
        'total' => ["''", '', 'true'],
        'path' => ['e.path', '', "e.type = 'pageview'"],
        'page_type' => ['e.page_type', '', "e.type = 'pageview'"],
        'job' => ['CAST(e.job_id AS CHAR)', '', 'e.job_id IS NOT NULL'],
        'category' => ['CAST(e.category_id AS CHAR)', '', 'e.category_id IS NOT NULL'],
        'referrer' => ['e.referrer_host', '', "e.type = 'pageview' AND e.referrer_host IS NOT NULL"],
        'utm_source' => ['e.utm_source', '', "e.type = 'pageview' AND e.utm_source IS NOT NULL"],
        'utm_campaign' => ['e.utm_campaign', '', "e.type = 'pageview' AND e.utm_campaign IS NOT NULL"],
        'device' => ['e.device', '', "e.type = 'pageview'"],
        'search' => ['e.search_query', '', 'e.search_query IS NOT NULL AND e.result_count IS NOT NULL'],
        'filter' => [
            'f.name',
            " CROSS JOIN JSON_TABLE(e.search_filters, '$[*]' COLUMNS (name VARCHAR(100) PATH '$')) AS f",
            'e.result_count IS NOT NULL',
        ],
        'channel' => ['e.apply_channel', '', 'e.apply_channel IS NOT NULL'],
    ];

    public function __construct(private readonly Database $db)
    {
    }

    public function record(AnalyticsEvent $event): void
    {
        $page = $event->page;
        $this->db->execute(
            'INSERT INTO analytics_events (type, visitor_hash, path, page_type, job_id, category_id, referrer_host,
                utm_source, utm_medium, utm_campaign, device, apply_channel, search_query, search_filters, result_count)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $event->type(), $event->visitorHash, $event->path, $page->pageType->value, $page->jobId, $page->categoryId,
                $event->referrerHost, $event->utmSource, $event->utmMedium, $event->utmCampaign, $event->device,
                $event->applyChannel?->value, $page->searchQuery,
                json_encode(array_values($page->searchFilters), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                $page->resultCount,
            ],
        );
    }

    public function latestRolledDay(): ?DateTimeImmutable
    {
        $value = $this->db->fetchValue('SELECT max(day) FROM analytics_daily');

        return is_string($value) ? new DateTimeImmutable($value) : null;
    }

    public function earliestEventAt(): ?DateTimeImmutable
    {
        return DbTime::fromDb($this->db->fetchValue('SELECT min(occurred_at) FROM analytics_events'));
    }

    public function rollUpDay(DateTimeImmutable $day, DateTimeImmutable $start, DateTimeImmutable $end): void
    {
        $branches = [];
        $params = [$day->format('Y-m-d')];
        foreach (self::ROLLUP as $dimension => [$key, $join, $where]) {
            $branches[] = "SELECT '{$dimension}' AS dimension, {$key} AS `key`, e.type, e.visitor_hash, e.result_count
                           FROM analytics_events e{$join}
                           WHERE e.occurred_at >= ? AND e.occurred_at < ? AND {$where}";
            array_push($params, DbTime::toDb($start), DbTime::toDb($end));
        }

        $this->db->transaction(function (Database $db) use ($branches, $params, $day): void {
            $db->execute('DELETE FROM analytics_daily WHERE day = ?', [$day->format('Y-m-d')]);
            $db->execute(
                "INSERT INTO analytics_daily (day, dimension, `key`, pageviews, visitors, applies, searches, zero_results)
                 SELECT CAST(? AS DATE), d.dimension, d.`key`,
                        sum(d.type = 'pageview'),
                        count(DISTINCT CASE WHEN d.type = 'pageview' THEN d.visitor_hash END),
                        sum(d.type = 'apply_click'),
                        sum(d.result_count IS NOT NULL),
                        coalesce(sum(d.result_count = 0), 0)
                 FROM (" . implode(' UNION ALL ', $branches) . ') AS d
                 GROUP BY d.dimension, d.`key`',
                $params,
            );
        });
    }

    public function purgeEventsBefore(DateTimeImmutable $cutoff): int
    {
        return $this->db->execute('DELETE FROM analytics_events WHERE occurred_at < ?', [DbTime::toDb($cutoff)]);
    }

    public function totals(DateRange $range, AnalyticsDimension $dimension = AnalyticsDimension::Total, ?string $key = ''): array
    {
        $row = $this->db->fetchOne(
            'SELECT ' . self::SUMS . ' FROM analytics_daily
             WHERE dimension = ? AND (? IS NULL OR `key` = ?) AND day BETWEEN ? AND ?',
            [$dimension->value, $key, $key, ...$this->bounds($range)],
        ) ?? [];

        return array_map('intval', $row);
    }

    public function daily(DateRange $range, AnalyticsDimension $dimension = AnalyticsDimension::Total, string $key = ''): array
    {
        $rows = $this->db->fetchAll(
            'SELECT day, ' . self::SUMS . ' FROM analytics_daily
             WHERE dimension = ? AND `key` = ? AND day BETWEEN ? AND ? GROUP BY day ORDER BY day',
            [$dimension->value, $key, ...$this->bounds($range)],
        );
        $byDay = [];
        foreach ($rows as $row) {
            $day = (string) $row['day'];
            unset($row['day']);
            $byDay[$day] = array_map('intval', $row);
        }

        return $byDay;
    }

    public function top(AnalyticsDimension $dimension, DateRange $range, string $sort = 'pageviews', ?int $limit = 10, bool $onlyZeroResults = false): array
    {
        if (!in_array($sort, self::SORTS, true)) {
            throw new InvalidArgumentException("Unknown analytics sort: {$sort}");
        }
        [$select, $join] = match ($dimension) {
            AnalyticsDimension::Job => [
                ', j.title, j.slug, j.status, j.deleted_at IS NOT NULL AS deleted',
                ' LEFT JOIN jobs j ON j.id = CAST(a.`key` AS UNSIGNED)',
            ],
            AnalyticsDimension::Category => [', c.name, c.slug', ' LEFT JOIN job_categories c ON c.id = CAST(a.`key` AS UNSIGNED)'],
            default => ['', ''],
        };

        $sql = 'SELECT a.*' . $select . ' FROM (
                    SELECT `key`, ' . self::SUMS . ' FROM analytics_daily
                    WHERE dimension = ? AND day BETWEEN ? AND ? GROUP BY `key`
                ) AS a' . $join
            . ($onlyZeroResults ? ' WHERE a.zero_results > 0' : '')
            . " ORDER BY a.{$sort} DESC, a.pageviews DESC, a.`key`"
            . ($limit !== null ? ' LIMIT ' . max(1, $limit) : '');

        return $this->db->fetchAll($sql, [$dimension->value, ...$this->bounds($range)]);
    }

    /** @return array{0: string, 1: string} */
    private function bounds(DateRange $range): array
    {
        return [$range->from->format('Y-m-d'), $range->to->format('Y-m-d')];
    }
}
