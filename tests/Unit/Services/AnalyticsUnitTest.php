<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Controllers\Admin\AnalyticsPresenter;
use App\Controllers\Admin\LineChart;
use App\Domain\AnalyticsDimension;
use App\Domain\AnalyticsEvent;
use App\Domain\AnalyticsMetrics;
use App\Domain\DateRange;
use App\Repositories\Contracts\AnalyticsRepositoryInterface;
use App\Repositories\Contracts\LockInterface;
use App\Repositories\Contracts\VisitorSaltRepositoryInterface;
use App\Services\AnalyticsRollup;
use App\Services\BotDetector;
use App\Services\DeviceClassifier;
use App\Services\VisitorHasher;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AnalyticsUnitTest extends TestCase
{
    private const CHROME_DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';
    private const CHROME_ANDROID = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36';
    private const ANDROID_TABLET = 'Mozilla/5.0 (Linux; Android 13; SM-X200) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';
    private const IPAD = 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    public function testBotDetection(): void
    {
        $bots = new BotDetector();
        foreach ([
            '', '   ', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'facebookexternalhit/1.1', 'WhatsApp/2.23.20.0', 'curl/8.4.0', 'python-requests/2.31',
            'Mozilla/5.0 (X11; Linux x86_64) HeadlessChrome/120.0', 'UptimeRobot/2.0', 'GPTBot/1.0',
        ] as $agent) {
            $this->assertTrue($bots->isBot($agent), $agent);
        }
        foreach ([self::CHROME_DESKTOP, self::CHROME_ANDROID, self::IPAD] as $agent) {
            $this->assertFalse($bots->isBot($agent), $agent);
        }
    }

    public function testDeviceClasses(): void
    {
        $devices = new DeviceClassifier();
        $this->assertSame('desktop', $devices->classify(self::CHROME_DESKTOP));
        $this->assertSame('mobile', $devices->classify(self::CHROME_ANDROID));
        $this->assertSame('tablet', $devices->classify(self::ANDROID_TABLET));
        $this->assertSame('tablet', $devices->classify(self::IPAD));
        $this->assertSame('mobile', $devices->classify('Opera/9.80 (J2ME/MIDP; Opera Mini/9.80)'));
    }

    public function testVisitorHashIsStableForADayAndUnlinkableAcrossDays(): void
    {
        $salts = new class () implements VisitorSaltRepositoryInterface {
            /** @var array<string, string> */
            public array $salts = [];

            public function saltFor(DateTimeImmutable $day): string
            {
                return $this->salts[$day->format('Y-m-d')] ??= random_bytes(32);
            }
        };
        $hasher = new VisitorHasher($salts);
        $morning = new DateTimeImmutable('2026-10-27 08:00');

        $a = $hasher->hash('41.210.1.1', self::CHROME_ANDROID, $morning);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $a);
        $this->assertSame($a, $hasher->hash('41.210.1.1', self::CHROME_ANDROID, $morning->modify('+10 hours')));
        $this->assertNotSame($a, $hasher->hash('41.210.1.2', self::CHROME_ANDROID, $morning));
        $this->assertNotSame($a, $hasher->hash('41.210.1.1', self::CHROME_DESKTOP, $morning));
        $this->assertNotSame($a, $hasher->hash('41.210.1.1', self::CHROME_ANDROID, $morning->modify('+1 day')));
        $this->assertStringNotContainsString('41.210', $a);
    }

    public function testRollupStartsFromTheLastRolledDayAndPurgesOldEvents(): void
    {
        $repo = $this->rollupRepository(new DateTimeImmutable('2026-10-25'));
        $rolled = (new AnalyticsRollup($repo, $this->lock(true)))->catchUp(new DateTimeImmutable('2026-10-27 12:00'));

        $this->assertSame(3, $rolled);
        $this->assertSame(['2026-10-25', '2026-10-26', '2026-10-27'], $repo->rolled);
        $this->assertSame('2025-10-27', $repo->purgedBefore);
    }

    public function testRollupNeverRebuildsDaysPastRetention(): void
    {
        $repo = $this->rollupRepository(new DateTimeImmutable('2020-01-01'));
        (new AnalyticsRollup($repo, $this->lock(true)))->catchUp(new DateTimeImmutable('2026-10-27 12:00'));

        $this->assertSame('2025-10-27', $repo->rolled[0]);
        $this->assertCount(AnalyticsRollup::RETENTION_DAYS + 1, $repo->rolled);
    }

    public function testRollupSkipsWhenAnotherProcessHoldsTheLock(): void
    {
        $repo = $this->rollupRepository(null);
        $this->assertSame(0, (new AnalyticsRollup($repo, $this->lock(false)))->catchUp());
        $this->assertSame([], $repo->rolled);
    }

    public function testChartAxisUsesCleanNumbersAndPathsFollowThePoints(): void
    {
        $points = array_map(static fn (int $v): array => ['label' => "d{$v}", 'short' => "{$v}", 'value' => $v], [0, 37, 12, 90]);
        $chart = new LineChart('Visitors', $points, 720, 220);

        $this->assertSame(['0', '25', '50', '75', '100'], array_column($chart->yTicks(), 'label'));
        $this->assertSame(139, $chart->total());
        $this->assertStringStartsWith('M44 194', $chart->linePath());
        $this->assertStringEndsWith('Z', $chart->areaPath());
        $this->assertSame(194.0, $chart->baseline());
        $this->assertSame(['0', '5', '10', '15', '20'], array_column((new LineChart('x', [['label' => '', 'short' => '', 'value' => 15]]))->yTicks(), 'label'));
        $this->assertSame('1.2K', LineChart::compact(1200));
        $this->assertSame('3M', LineChart::compact(3_000_000));
    }

    public function testTilesShowChangeAgainstThePreviousPeriod(): void
    {
        $tiles = (new AnalyticsPresenter())->tiles([
            'totals' => new AnalyticsMetrics(pageviews: 300, visitors: 120, applies: 10),
            'previous' => new AnalyticsMetrics(pageviews: 300, visitors: 100, applies: 0),
            'jobViews' => 200,
            'previousJobViews' => 0,
        ]);
        $byLabel = array_column($tiles, null, 'label');

        $this->assertSame('120', $byLabel['Visitors']['value']);
        $this->assertSame(['text' => '+20%', 'dir' => 'up'], $byLabel['Visitors']['delta']);
        $this->assertSame(['text' => '0%', 'dir' => 'flat'], $byLabel['Page views']['delta']);
        $this->assertSame(['text' => 'New', 'dir' => 'up'], $byLabel['Apply clicks']['delta']);
        $this->assertSame('5%', $byLabel['Apply rate']['value']);
        $this->assertSame(['text' => '+5 pts', 'dir' => 'up'], $byLabel['Apply rate']['delta']);
        $this->assertArrayNotHasKey('New subscribers', $byLabel);
    }

    private function lock(bool $free): LockInterface
    {
        return new class ($free) implements LockInterface {
            public function __construct(private readonly bool $free)
            {
            }

            public function tryAcquire(string $name): bool
            {
                return $this->free;
            }

            public function release(string $name): void
            {
            }
        };
    }

    private function rollupRepository(?DateTimeImmutable $latest): AnalyticsRepositoryInterface
    {
        return new class ($latest) implements AnalyticsRepositoryInterface {
            /** @var list<string> */
            public array $rolled = [];

            public ?string $purgedBefore = null;

            public function __construct(private readonly ?DateTimeImmutable $latest)
            {
            }

            public function record(AnalyticsEvent $event): void
            {
            }

            public function latestRolledDay(): ?DateTimeImmutable
            {
                return $this->latest;
            }

            public function earliestEventAt(): ?DateTimeImmutable
            {
                return null;
            }

            public function rollUpDay(DateTimeImmutable $day, DateTimeImmutable $start, DateTimeImmutable $end): void
            {
                $this->rolled[] = $day->format('Y-m-d');
            }

            public function purgeEventsBefore(DateTimeImmutable $cutoff): int
            {
                $this->purgedBefore = $cutoff->format('Y-m-d');

                return 0;
            }

            public function totals(DateRange $range, AnalyticsDimension $dimension = AnalyticsDimension::Total, ?string $key = ''): array
            {
                return [];
            }

            public function daily(DateRange $range, AnalyticsDimension $dimension = AnalyticsDimension::Total, string $key = ''): array
            {
                return [];
            }

            public function top(AnalyticsDimension $dimension, DateRange $range, string $sort = 'pageviews', ?int $limit = 10, bool $onlyZeroResults = false): array
            {
                return [];
            }
        };
    }
}
