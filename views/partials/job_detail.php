<?php
/** @var \App\Domain\Job $job */
/** @var bool $inPane */
// Apply links go through /jobs/{slug}/apply/{link|email}, which counts the click and redirects.
use App\Core\Format;

$headingTag = $inPane ? 'h2' : 'h1';
?>
<<?= $headingTag ?> class="detail-title"><?php if ($inPane): ?><a href="/jobs/<?= e($job->slug) ?>"><?= e($job->title) ?></a><?php else: ?><?= e($job->title) ?><?php endif; ?></<?= $headingTag ?>>
<p class="company"><?= e($job->company) ?> · <a href="/jobs-in/<?= e(\App\Domain\Slug::from($job->location)) ?>"><?= e($job->location) ?></a><?= $job->isRemote ? ' · Remote' : '' ?></p>
<p class="salary"><?= e(Format::jobSalary($job)) ?><?php if ($job->positions > 1): ?> · <span class="positions"><?= e(Format::positions($job->positions)) ?></span><?php endif; ?></p>
<?php if ($job->expiresAt !== null): $dl = Format::deadline($job->expiresAt); ?>
  <div class="deadline-box <?= e($dl['state']) ?>">
    <span class="deadline-label">Application deadline</span>
    <strong><time datetime="<?= e($job->expiresAt->format('Y-m-d')) ?>"><?= e(Format::date($job->expiresAt, 'l, j F Y')) ?></time></strong>
    <span class="deadline-when"><?= e(Format::deadlineCountdown($job->expiresAt)) ?></span>
  </div>
<?php endif; ?>
<div class="actions">
  <?php if ($job->applyUrl !== null): ?>
    <a class="btn-primary" href="/jobs/<?= e($job->slug) ?>/apply/link" target="_blank" rel="noopener nofollow">Apply / more info ↗</a>
  <?php endif; ?>
  <?php if ($job->applyEmail !== null): ?>
    <a class="<?= $job->applyUrl !== null ? 'btn-ghost' : 'btn-primary' ?>" href="/jobs/<?= e($job->slug) ?>/apply/email" rel="nofollow">Apply by email</a>
  <?php endif; ?>
  <button class="btn-ghost js-only" type="button" data-save="<?= e($job->id) ?>" aria-pressed="false" hidden>☆ Save job</button>
  <button class="btn-ghost js-only" type="button" data-share-url="<?= e(($appUrl ?? '') . '/jobs/' . $job->slug) ?>" data-share-title="<?= e($job->title . ' at ' . $job->company) ?>" hidden><?= $this->insert('partials/icon_share') ?> Share</button>
  <?php if ($inPane): ?>
    <a class="btn-ghost" href="/jobs/<?= e($job->slug) ?>">Open full page</a>
  <?php endif; ?>
</div>
<p class="muted">
  <?= e($job->jobType->label()) ?> · <?= e($job->experienceLevel->label()) ?> level ·
  <a href="/categories/<?= e($job->categorySlug) ?>"><?= e($job->categoryName) ?></a> · <?= e(Format::posted($job->publishedAt)) ?>
</p>
<?php if ($job->reportsTo !== null): ?>
  <p class="reports-to"><strong>Reports to:</strong> <?= e($job->reportsTo) ?></p>
<?php endif; ?>
<h3>About the role</h3>
<p><?= e($job->summary) ?></p>
<?php if ($job->responsibilities !== []): ?>
  <h3>Responsibilities</h3>
  <ul><?php foreach ($job->responsibilities as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
<?php if ($job->requirements !== []): ?>
  <h3>Requirements</h3>
  <ul><?php foreach ($job->requirements as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
<?php endif; ?>
<?php if ($job->skills !== []): ?>
  <h3>Skills</h3>
  <div class="tags"><?php foreach ($job->skills as $skill): ?><span><?= e($skill) ?></span><?php endforeach; ?></div>
<?php endif; ?>
<h3>How to apply</h3>
<?php if ($job->applyUrl === null && $job->applyEmail === null): ?>
  <p class="muted">Contact <?= e($job->company) ?> directly.</p>
<?php else: ?>
  <ul class="apply-list">
    <?php if ($job->applyUrl !== null): ?>
      <li>Online: <a href="/jobs/<?= e($job->slug) ?>/apply/link" target="_blank" rel="noopener nofollow"><?= e(parse_url($job->applyUrl, PHP_URL_HOST) ?: $job->applyUrl) ?></a></li>
    <?php endif; ?>
    <?php if ($job->applyEmail !== null): ?>
      <li>By email: <a href="/jobs/<?= e($job->slug) ?>/apply/email" rel="nofollow"><?= e($job->applyEmail) ?></a></li>
    <?php endif; ?>
  </ul>
<?php endif; ?>
<?php if ($job->applicationInstructions !== null): ?>
  <p class="apply-instructions"><?= e($job->applicationInstructions) ?></p>
<?php endif; ?>
