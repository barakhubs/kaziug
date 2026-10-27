<?php

declare(strict_types=1);

namespace App\Controllers\Public;

use App\Core\Http\Request;
use App\Core\Http\Response;
use App\Domain\ApplyChannel;
use App\Domain\NotFoundException;
use App\Services\Contracts\AnalyticsRecorderInterface;
use App\Services\Contracts\JobSearchServiceInterface;

/**
 * The "Apply" buttons go through here so apply clicks can be counted without JavaScript. The
 * destination always comes from the stored job, never from the request, so this is no open redirect.
 */
final class ApplyController
{
    public function __construct(
        private readonly JobSearchServiceInterface $search,
        private readonly AnalyticsRecorderInterface $recorder,
    ) {
    }

    public function redirect(Request $request): Response
    {
        $job = $this->search->findBySlug((string) $request->attribute('slug'));
        $channel = ApplyChannel::from((string) $request->attribute('channel'));
        $target = match ($channel) {
            ApplyChannel::Link => $job->applyUrl,
            ApplyChannel::Email => $job->applyEmail === null
                ? null
                : 'mailto:' . $job->applyEmail . '?subject=' . rawurlencode('Application: ' . $job->title),
        };
        if ($target === null) {
            throw new NotFoundException('This job has no application ' . ($channel === ApplyChannel::Link ? 'link.' : 'email.'));
        }

        $this->recorder->recordApplyClick($request, $job, $channel);

        return Response::redirect($target, 302)
            ->setHeader('Cache-Control', 'no-store')
            ->setHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
