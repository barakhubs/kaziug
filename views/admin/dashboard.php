<?php
/** @var array<string, int> $jobs */
/** @var array<string, int> $subscribers */
/** @var \App\Domain\Newsletter|null $latestNewsletter */
/** @var \App\Domain\DeliveryCounts|null $latestCounts */
/** @var list<\App\Domain\Category> $categories */
/** @var list<array<string, mixed>> $recentAudit */
/** @var bool $mailConfigured */
/** @var \App\Domain\SiteSettings $site */
use App\Core\Format;

$this->layout('layouts/admin');
$this->set('title', 'Dashboard');
$withJobs = array_values(array_filter($categories, static fn ($c) => $c->jobCount > 0));
usort($withJobs, static fn ($a, $b) => $b->jobCount <=> $a->jobCount);
$max = max([1, ...array_map(static fn ($c) => $c->jobCount, $withJobs)]);
?>
<header class="page-head">
  <h1>Dashboard</h1>
  <div class="head-actions">
    <a class="btn-ghost" href="/admin/newsletters/new">New newsletter</a>
    <a class="btn-primary" href="/admin/jobs/new">+ New job</a>
  </div>
</header>

<?php if (!$mailConfigured): ?>
  <div class="flash info">Email isn’t set up yet, so subscription confirmations and newsletters can’t be sent. <a href="/admin/settings/smtp">Configure SMTP</a>.</div>
<?php endif; ?>
<?php if ($site->maintenanceMode): ?>
  <div class="flash info">Maintenance mode is on: visitors see a maintenance page. <a href="/admin/settings">Site settings</a>.</div>
<?php endif; ?>

<div class="stats">
  <a class="stat stat-link" href="/admin/jobs?status=published"><span>Published jobs</span><strong><?= e($jobs['published'] ?? 0) ?></strong></a>
  <a class="stat stat-link" href="/admin/jobs?status=draft"><span>Drafts</span><strong><?= e($jobs['draft'] ?? 0) ?></strong></a>
  <a class="stat stat-link" href="/admin/subscribers?status=active"><span>Active subscribers</span><strong><?= e($subscribers['active'] ?? 0) ?></strong></a>
  <?php if ($latestNewsletter !== null): ?>
    <a class="stat stat-link" href="/admin/newsletters/<?= e($latestNewsletter->id) ?>">
      <span>Last newsletter</span>
      <strong class="stat-text"><?= e(mb_strimwidth($latestNewsletter->subject, 0, 40, '…')) ?></strong>
      <span class="small"><span class="pill <?= e($latestNewsletter->status->value) ?>"><?= e($latestNewsletter->status->label()) ?></span>
        <?= e($latestCounts?->sent ?? 0) ?> sent · <?= e($latestCounts?->queued ?? 0) ?> queued · <?= e($latestCounts?->failed ?? 0) ?> failed</span>
    </a>
  <?php else: ?>
    <div class="stat"><span>Last newsletter</span><strong class="stat-text">None yet</strong></div>
  <?php endif; ?>
</div>

<div class="dash-grid">
  <section class="panel">
    <h2>Jobs by category</h2>
    <?php if ($withJobs === []): ?>
      <p class="muted">No jobs yet.</p>
    <?php endif; ?>
    <?php foreach ($withJobs as $category): ?>
      <div class="bar-row">
        <span><?= e($category->name) ?></span>
        <svg class="bar" viewBox="0 0 100 10" preserveAspectRatio="none" role="img" aria-label="<?= e($category->jobCount) ?> jobs">
          <rect width="<?= e(round($category->jobCount / $max * 100, 1)) ?>" height="10" rx="5"></rect>
        </svg>
        <b><?= e($category->jobCount) ?></b>
      </div>
    <?php endforeach; ?>
  </section>

  <section class="panel">
    <div class="panel-head"><h2>Recent activity</h2><a href="/admin/audit">Full audit log →</a></div>
    <?= $this->insert('partials/audit_rows', ['rows' => $recentAudit]) ?>
  </section>
</div>
