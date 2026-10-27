<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Domain\AnalyticsMetrics;
use DateTimeImmutable;

/**
 * Turns analytics report data into what the templates print: KPI tiles with their change against
 * the previous period, and chart geometry. Keeps arithmetic and formatting out of the views.
 */
final class AnalyticsPresenter
{
    /**
     * @param array{totals: AnalyticsMetrics, previous: AnalyticsMetrics, jobViews: int, previousJobViews: int} $headline
     * @param array{signups: int, confirmations: int, unsubscribes: int}|null $subscribers
     * @param array{signups: int, confirmations: int, unsubscribes: int}|null $previousSubscribers
     * @return list<array{label: string, value: string, delta: array{text: string, dir: string}|null, hint: string}>
     */
    public function tiles(array $headline, ?array $subscribers = null, ?array $previousSubscribers = null): array
    {
        [$now, $before] = [$headline['totals'], $headline['previous']];
        $rate = static fn (int $applies, int $views): float => $views === 0 ? 0.0 : $applies / $views;

        $tiles = [
            $this->count('Visitors', $now->visitors, $before->visitors, 'Unique visitors per day, added up'),
            $this->count('Page views', $now->pageviews, $before->pageviews, 'Public pages viewed'),
            $this->count('Job views', $headline['jobViews'], $headline['previousJobViews'], 'Job pages, plus jobs opened in the list'),
            $this->count('Apply clicks', $now->applies, $before->applies, 'Clicks on an application link or email'),
            [
                'label' => 'Apply rate',
                'value' => self::percent($rate($now->applies, $headline['jobViews'])),
                'delta' => $this->pointsDelta($rate($now->applies, $headline['jobViews']), $rate($before->applies, $headline['previousJobViews'])),
                'hint' => 'Apply clicks per job view',
            ],
        ];
        if ($subscribers !== null && $previousSubscribers !== null) {
            $tiles[] = $this->count('New subscribers', $subscribers['confirmations'], $previousSubscribers['confirmations'], 'Confirmed job alert sign-ups');
        }

        return $tiles;
    }

    /** @param list<array{day: DateTimeImmutable, metrics: AnalyticsMetrics}> $series */
    public function chart(string $title, array $series, string $metric, int $height = 220, int $width = 960): LineChart
    {
        return new LineChart($title, array_map(static fn (array $point): array => [
            'label' => $point['day']->format('D j M Y'),
            'short' => $point['day']->format('j M'),
            'value' => $point['metrics']->{$metric},
        ], $series), $width, $height);
    }

    public static function percent(float $ratio): string
    {
        return $ratio === 0.0 ? '0%' : rtrim(rtrim(number_format($ratio * 100, 1), '0'), '.') . '%';
    }

    /** @return array{label: string, value: string, delta: array{text: string, dir: string}|null, hint: string} */
    private function count(string $label, int $now, int $before, string $hint): array
    {
        return ['label' => $label, 'value' => number_format($now), 'delta' => $this->delta($now, $before), 'hint' => $hint];
    }

    /** @return array{text: string, dir: string}|null null when there is nothing to compare with */
    private function delta(int $now, int $before): ?array
    {
        if ($before === 0) {
            return $now === 0 ? null : ['text' => 'New', 'dir' => 'up'];
        }
        $change = ($now - $before) / $before;

        return ['text' => ($change > 0 ? '+' : ($change < 0 ? '−' : '')) . self::percent(abs($change)), 'dir' => self::direction($change)];
    }

    /** @return array{text: string, dir: string}|null */
    private function pointsDelta(float $now, float $before): ?array
    {
        if ($now === 0.0 && $before === 0.0) {
            return null;
        }
        $points = ($now - $before) * 100;

        return [
            'text' => ($points > 0 ? '+' : ($points < 0 ? '−' : '')) . rtrim(rtrim(number_format(abs($points), 1), '0'), '.') . ' pts',
            'dir' => self::direction($points),
        ];
    }

    private static function direction(float $change): string
    {
        return match (true) {
            $change > 0.0005 => 'up',
            $change < -0.0005 => 'down',
            default => 'flat',
        };
    }
}
