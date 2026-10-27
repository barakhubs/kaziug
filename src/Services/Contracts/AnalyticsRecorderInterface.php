<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Core\Http\Request;
use App\Domain\ApplyChannel;
use App\Domain\Job;
use App\Domain\PageContext;

/** Records anonymous page views and apply clicks. Never throws: analytics must not break a page. */
interface AnalyticsRecorderInterface
{
    public function recordPageView(Request $request, PageContext $page): void;

    public function recordApplyClick(Request $request, Job $job, ApplyChannel $channel): void;
}
