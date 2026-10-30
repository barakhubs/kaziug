<?php
/** @var \App\Domain\Job $job */
/** @var bool $active */
/** @var string $paneUrl */
use App\Core\Format;

$shareUrl = ($appUrl ?? '') . '/jobs/' . $job->slug;
?>
<li class="card<?= $active ? ' active' : '' ?>" data-pane-url="<?= e($paneUrl) ?>">
  <div class="card-top">
    <div>
      <h3 class="title"><a href="/jobs/<?= e($job->slug) ?>"><?= e($job->title) ?></a></h3>
      <p class="company"><?= e($job->company) ?> · <?= e($job->location) ?><?= $job->isRemote ? ' · Remote' : '' ?></p>
    </div>
    <div class="card-actions">
      <button class="share js-only" type="button" data-share-url="<?= e($shareUrl) ?>" data-share-title="<?= e($job->title . ' at ' . $job->company) ?>" aria-label="Share <?= e($job->title) ?>" title="Share or copy link" hidden><?= $this->insert('partials/icon_share') ?></button>
      <button class="save js-only" type="button" data-save="<?= e($job->id) ?>" aria-pressed="false" aria-label="Save <?= e($job->title) ?>" hidden>☆</button>
    </div>
  </div>
  <p class="salary"><?= e(Format::jobSalary($job)) ?></p>
  <p class="snippet"><?= e($job->summary) ?></p>
  <div class="tags"><span><?= e($job->jobType->label()) ?></span><span><?= e($job->experienceLevel->label()) ?> level</span><span><?= e($job->categoryName) ?></span><?php if ($job->positions > 1): ?><span class="tag-positions"><?= e(Format::positions($job->positions)) ?></span><?php endif; ?></div>
  <p class="posted">
    <?= e(Format::posted($job->publishedAt)) ?>
    <?php if ($job->expiresAt !== null): $dl = Format::deadline($job->expiresAt); ?>
      <span class="deadline-pill <?= e($dl['state']) ?>" title="Application deadline: <?= e(Format::date($job->expiresAt, 'l, j F Y')) ?>"><?= e($dl['text']) ?></span>
    <?php endif; ?>
  </p>
</li>
