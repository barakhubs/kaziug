<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Http\Request;
use App\Core\Log\LoggerInterface;
use App\Domain\AnalyticsEvent;
use App\Domain\ApplyChannel;
use App\Domain\Job;
use App\Domain\PageContext;
use App\Repositories\Contracts\AnalyticsRepositoryInterface;
use App\Services\Contracts\AnalyticsRecorderInterface;
use Throwable;

final class AnalyticsRecorder implements AnalyticsRecorderInterface
{
    private const MAX_PATH = 300;
    private const MAX_UTM = 100;

    public function __construct(
        private readonly AnalyticsRepositoryInterface $repository,
        private readonly TrafficFilter $filter,
        private readonly VisitorHasher $hasher,
        private readonly DeviceClassifier $devices,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function recordPageView(Request $request, PageContext $page): void
    {
        $this->record($request, $page, null);
    }

    public function recordApplyClick(Request $request, Job $job, ApplyChannel $channel): void
    {
        $this->record($request, PageContext::job($job), $channel);
    }

    private function record(Request $request, PageContext $page, ?ApplyChannel $channel): void
    {
        try {
            if (!$this->filter->shouldCount($request)) {
                return;
            }
            $this->repository->record(new AnalyticsEvent(
                $this->hasher->hash($request->ip(), $request->userAgent()),
                mb_substr($request->path, 0, self::MAX_PATH),
                $this->devices->classify($request->userAgent()),
                $page,
                $channel,
                $this->referrerHost($request),
                $this->utm($request, 'utm_source'),
                $this->utm($request, 'utm_medium'),
                $this->utm($request, 'utm_campaign'),
            ));
        } catch (Throwable $e) {
            // Only the exception type and message: never the request, which could hold personal data.
            $this->logger->warning('Analytics event not recorded', ['error' => $e::class . ': ' . $e->getMessage()]);
        }
    }

    /** The referring site's host name (no path or query), or null for internal and direct visits. */
    private function referrerHost(Request $request): ?string
    {
        $referrer = $request->header('referer');
        $host = is_string($referrer) ? parse_url($referrer, PHP_URL_HOST) : null;
        if (!is_string($host) || $host === '') {
            return null;
        }
        $host = (string) preg_replace('/^www\./', '', strtolower($host));
        $own = array_filter([
            parse_url($this->config->string('app.url'), PHP_URL_HOST),
            explode(':', (string) $request->header('host'))[0],
        ]);
        foreach ($own as $ownHost) {
            if ($host === preg_replace('/^www\./', '', strtolower((string) $ownHost))) {
                return null;
            }
        }

        return mb_substr($host, 0, 255);
    }

    private function utm(Request $request, string $key): ?string
    {
        $value = $request->query($key);
        if (!is_string($value)) {
            return null;
        }
        $value = mb_substr(mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $value))), 0, self::MAX_UTM);

        return $value === '' ? null : $value;
    }
}
