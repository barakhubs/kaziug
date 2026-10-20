<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Contracts\AuditServiceInterface;
use App\Services\Contracts\CategoryServiceInterface;
use App\Services\Contracts\DashboardServiceInterface;
use App\Services\Contracts\JobServiceInterface;
use App\Services\Contracts\NewsletterServiceInterface;
use App\Services\Contracts\SmtpSettingsServiceInterface;
use App\Services\Contracts\SubscriberAdminServiceInterface;

final class DashboardService implements DashboardServiceInterface
{
    public function __construct(
        private readonly JobServiceInterface $jobs,
        private readonly SubscriberAdminServiceInterface $subscribers,
        private readonly NewsletterServiceInterface $newsletters,
        private readonly CategoryServiceInterface $categories,
        private readonly AuditServiceInterface $audit,
        private readonly SmtpSettingsServiceInterface $smtp,
    ) {
    }

    public function summary(): array
    {
        $latest = $this->newsletters->latest();

        return [
            'jobs' => $this->jobs->countByStatus(),
            'subscribers' => $this->subscribers->countByStatus(),
            'latestNewsletter' => $latest,
            'latestCounts' => $latest === null ? null : $this->newsletters->counts($latest->id),
            'categories' => $this->categories->all(),
            'recentAudit' => $this->audit->recent(10),
            'mailConfigured' => $this->smtp->settings()->isConfigured(),
        ];
    }
}
