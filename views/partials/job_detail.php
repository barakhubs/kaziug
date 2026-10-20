<?php
/** @var \App\Domain\Job $job */
/** @var bool $inPane */
use App\Core\Format;

$headingTag = $inPane ? 'h2' : 'h1';
?>
<<?= $headingTag ?> class="detail-title"><?php if ($inPane): ?><a href="/jobs/<?= e($job->slug) ?>"><?= e($job->title) ?></a><?php else: ?><?= e($job->title) ?><?php endif; ?></<?= $headingTag ?>>
<p class="company"><?= e($job->company) ?> · <?= e($job->location) ?><?= $job->isRemote ? ' · Remote' : '' ?></p>
<p class="salary"><?= e(Format::salary($job->salaryMin, $job->salaryMax, $job->currency)) ?></p>
<div class="actions">
  <?php if ($job->applyUrl !== null): ?>
    <a class="btn-primary" href="<?= e($job->applyUrl) ?>" target="_blank" rel="noopener nofollow">Apply now</a>
  <?php elseif ($job->applyEmail !== null): ?>
    <a class="btn-primary" href="mailto:<?= e($job->applyEmail) ?>?subject=<?= e(rawurlencode('Application: ' . $job->title)) ?>">Apply by email</a>
  <?php endif; ?>
  <button class="btn-ghost js-only" type="button" data-save="<?= e($job->id) ?>" aria-pressed="false" hidden>☆ Save job</button>
  <?php if ($inPane): ?>
    <a class="btn-ghost" href="/jobs/<?= e($job->slug) ?>">Open full page</a>
  <?php endif; ?>
</div>
<p class="muted">
  <?= e($job->jobType->label()) ?> · <?= e($job->experienceLevel->label()) ?> level ·
  <a href="/categories/<?= e($job->categorySlug) ?>"><?= e($job->categoryName) ?></a> · <?= e(Format::posted($job->publishedAt)) ?>
  <?php if ($job->expiresAt !== null): ?> · Closes <?= e(Format::date($job->expiresAt)) ?><?php endif; ?>
</p>
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
<?php if ($job->applyUrl === null && $job->applyEmail === null): ?>
  <p class="muted apply-note">To apply, contact <?= e($job->company) ?> directly.</p>
<?php endif; ?>
