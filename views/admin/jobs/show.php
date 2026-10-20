<?php
/** @var \App\Domain\Job $job */
/** @var list<array<string, mixed>> $history */
use App\Core\Format;

$this->layout('layouts/admin');
$this->set('title', $job->title);
?>
<header class="page-head">
  <div>
    <h1><?= e($job->title) ?></h1>
    <p class="muted"><?= e($job->company) ?> · <?= e($job->location) ?><?= $job->isRemote ? ' · Remote' : '' ?></p>
  </div>
  <div class="head-actions">
    <a class="btn-ghost" href="/admin/jobs">← All jobs</a>
    <?php if ($job->isPubliclyVisible()): ?>
      <a class="btn-ghost" href="/jobs/<?= e($job->slug) ?>" target="_blank" rel="noopener">View on site ↗</a>
    <?php endif; ?>
    <a class="btn-primary" href="/admin/jobs/<?= e($job->id) ?>/edit">Edit</a>
  </div>
</header>

<div class="edit-layout">
  <section class="panel">
    <h2>Details</h2>
    <dl class="kv">
      <dt>Category</dt><dd><?= e($job->categoryName) ?></dd>
      <dt>Job type</dt><dd><?= e($job->jobType->label()) ?></dd>
      <dt>Experience</dt><dd><?= e($job->experienceLevel->label()) ?></dd>
      <dt>Salary</dt><dd><?= e(Format::salary($job->salaryMin, $job->salaryMax, $job->currency)) ?></dd>
      <dt>Posted</dt><dd><?= e($job->publishedAt !== null ? Format::dateTime($job->publishedAt) : 'Not published yet') ?></dd>
      <dt>Expires</dt><dd><?= e($job->expiresAt !== null ? Format::date($job->expiresAt) . ($job->isExpired() ? ' (expired)' : '') : 'No expiry') ?></dd>
      <dt>Skills</dt><dd><?= e($job->skills !== [] ? implode(', ', $job->skills) : '—') ?></dd>
      <dt>Public URL</dt><dd class="small">/jobs/<?= e($job->slug) ?></dd>
    </dl>

    <h2 class="section-gap">How to apply</h2>
    <dl class="kv">
      <dt>Link</dt><dd><?php if ($job->applyUrl !== null): ?><a href="<?= e($job->applyUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e($job->applyUrl) ?></a><?php else: ?>—<?php endif; ?></dd>
      <dt>Email</dt><dd><?= e($job->applyEmail ?? '—') ?></dd>
    </dl>
    <?php if ($job->applyUrl === null && $job->applyEmail === null): ?>
      <p class="field-error">No application details yet. <a href="/admin/jobs/<?= e($job->id) ?>/edit">Add them</a>.</p>
    <?php endif; ?>

    <h2 class="section-gap">Summary</h2>
    <p><?= e($job->summary) ?></p>
    <?php foreach (['Responsibilities' => $job->responsibilities, 'Requirements' => $job->requirements] as $heading => $items): ?>
      <?php if ($items !== []): ?>
        <h3><?= e($heading) ?></h3>
        <ul><?php foreach ($items as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    <?php endforeach; ?>
  </section>

  <aside class="panel status-panel">
    <h2>Status</h2>
    <p><span class="pill <?= e($job->status->value) ?>"><?= e($job->status->label()) ?></span>
      <?php if ($job->isExpired()): ?><span class="pill closed">Expired</span><?php endif; ?></p>
    <?php if ($job->status->allowedNext() !== []): ?>
      <form method="post" action="/admin/jobs/<?= e($job->id) ?>/status" class="form-actions">
        <?= $this->insert('partials/csrf') ?>
        <?php foreach ($job->status->allowedNext() as $next): ?>
          <button class="btn-ghost btn-sm" type="submit" name="to" value="<?= e($next->value) ?>"><?= e($job->status->actionLabel($next)) ?></button>
        <?php endforeach; ?>
      </form>
    <?php else: ?>
      <p class="hint">Archived jobs can't change status.</p>
    <?php endif; ?>

    <h2 class="section-gap">History</h2>
    <ol class="timeline">
      <?php foreach ($history as $entry): ?>
        <li>
          <strong><?= e($entry['from_status'] === null ? 'Created as ' . $entry['to_status'] : $entry['from_status'] . ' → ' . $entry['to_status']) ?></strong>
          <div class="muted small"><?= e(Format::dbDateTime($entry['changed_at'])) ?><?= $entry['changed_by'] !== null ? ' · ' . e($entry['changed_by']) : '' ?></div>
        </li>
      <?php endforeach; ?>
    </ol>

    <div class="form-actions section-gap">
      <form method="post" action="/admin/jobs/<?= e($job->id) ?>/duplicate">
        <?= $this->insert('partials/csrf') ?>
        <button class="btn-ghost btn-sm" type="submit">Duplicate</button>
      </form>
      <form method="post" action="/admin/jobs/<?= e($job->id) ?>/delete" data-confirm="Delete this job? It will disappear from the site and the back office.">
        <?= $this->insert('partials/csrf') ?>
        <button class="btn-danger btn-sm" type="submit">Delete</button>
      </form>
    </div>
  </aside>
</div>
