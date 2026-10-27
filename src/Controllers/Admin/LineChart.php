<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

/**
 * Geometry for a single-series daily line chart drawn as inline SVG: the line and area paths,
 * clean y-axis ticks and a few date labels. Templates only print what this computes.
 */
final class LineChart
{
    private const PAD_LEFT = 44;
    private const PAD_RIGHT = 14;
    private const PAD_TOP = 12;
    private const PAD_BOTTOM = 26;
    // Room each date label needs, in viewBox units, so narrow charts get fewer of them.
    private const X_LABEL_SPACE = 90;

    /**
     * @param list<array{label: string, short: string, value: int}> $points one per day, oldest first
     * @param int $height viewBox height; the width scales to the container
     */
    public function __construct(
        public readonly string $title,
        public readonly array $points,
        public readonly int $width = 720,
        public readonly int $height = 220,
    ) {
    }

    /** @return list<array{x: float, y: float, label: string, value: int}> */
    public function marks(): array
    {
        $count = count($this->points);
        $plotWidth = $this->width - self::PAD_LEFT - self::PAD_RIGHT;
        $max = $this->axisMax();

        $marks = [];
        foreach ($this->points as $i => $point) {
            $marks[] = [
                'x' => round(self::PAD_LEFT + ($count === 1 ? $plotWidth / 2 : $plotWidth * $i / ($count - 1)), 1),
                'y' => round($this->yFor($point['value'], $max), 1),
                'label' => $point['label'],
                'value' => $point['value'],
            ];
        }

        return $marks;
    }

    public function linePath(): string
    {
        $path = '';
        foreach ($this->marks() as $i => $mark) {
            $path .= ($i === 0 ? 'M' : 'L') . $mark['x'] . ' ' . $mark['y'];
        }

        return $path;
    }

    public function areaPath(): string
    {
        $marks = $this->marks();
        if ($marks === []) {
            return '';
        }
        $base = $this->baseline();

        // The line path starts with "M"; continuing it from the baseline turns that into a "L".
        return 'M' . $marks[0]['x'] . ' ' . $base . 'L' . substr($this->linePath(), 1) . 'L' . end($marks)['x'] . ' ' . $base . 'Z';
    }

    public function baseline(): float
    {
        return (float) ($this->height - self::PAD_BOTTOM);
    }

    public function left(): int
    {
        return self::PAD_LEFT;
    }

    public function right(): int
    {
        return $this->width - self::PAD_RIGHT;
    }

    /** @return list<array{y: float, label: string}> gridlines at clean values, 0 included */
    public function yTicks(): array
    {
        $max = $this->axisMax();
        $step = $max / 4;
        $ticks = [];
        for ($i = 0; $i <= 4; $i++) {
            $value = (int) round($step * $i);
            $ticks[] = ['y' => round($this->yFor($value, $max), 1), 'label' => self::compact($value)];
        }

        return $ticks;
    }

    /** @return list<array{x: float, label: string}> */
    public function xTicks(): array
    {
        $marks = $this->marks();
        $count = count($marks);
        if ($count === 0) {
            return [];
        }
        $labels = max(2, intdiv($this->right() - self::PAD_LEFT, self::X_LABEL_SPACE));
        $every = max(1, (int) ceil($count / $labels));
        $ticks = [];
        foreach ($marks as $i => $mark) {
            if ($i % $every === 0 || $i === $count - 1 && ($count - 1) % $every >= $every / 2) {
                $ticks[] = ['x' => $mark['x'], 'label' => $this->points[$i]['short']];
            }
        }

        return $ticks;
    }

    public function total(): int
    {
        return array_sum(array_column($this->points, 'value'));
    }

    public static function compact(int $value): string
    {
        return match (true) {
            $value >= 1_000_000 => rtrim(rtrim(number_format($value / 1_000_000, 1), '0'), '.') . 'M',
            $value >= 1_000 => rtrim(rtrim(number_format($value / 1_000, 1), '0'), '.') . 'K',
            default => (string) $value,
        };
    }

    /** The top of the y-axis: the smallest "nice" number (1, 2, 4, 5 or 10 × 10^n, ×4 ticks) above the data. */
    private function axisMax(): int
    {
        $max = max([0, ...array_column($this->points, 'value')]);
        if ($max <= 4) {
            return 4;
        }
        $magnitude = 10 ** (int) floor(log10($max / 4));
        foreach ([1, 2, 2.5, 5, 10] as $factor) {
            $step = $factor * $magnitude;
            if ($step * 4 >= $max && floor($step) == $step) {
                return (int) ($step * 4);
            }
        }

        return (int) (10 * $magnitude * 4);
    }

    private function yFor(int $value, int $max): float
    {
        $plotHeight = $this->height - self::PAD_TOP - self::PAD_BOTTOM;

        return self::PAD_TOP + $plotHeight * (1 - $value / max(1, $max));
    }
}
