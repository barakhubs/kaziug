<?php
/** @var \App\Domain\Job $job */
/** @var bool $active */
/** @var string $paneUrl */
use App\Core\Format;
?>
<li class="card<?= $active ? ' active' : '' ?>" data-pane-url="<?= e($paneUrl) ?>">
  <div class="card-top">
    <div>
      <h3 class="title"><a href="/jobs/<?= e($job->slug) ?>"><?= e($job->title) ?></a></h3>
      <p class="company"><?= e($job->company) ?> · <?= e($job->location) ?><?= $job->isRemote ? ' · Remote' : '' ?></p>
    </div>
    <button class="save js-only" type="button" data-save="<?= e($job->id) ?>" aria-pressed="false" aria-label="Save <?= e($job->title) ?>" hidden>☆</button>
  </div>
  <p class="salary"><?= e(Format::salary($job->salaryMin, $job->salaryMax, $job->currency)) ?></p>
  <p class="snippet"><?= e($job->summary) ?></p>
  <div class="tags"><span><?= e($job->jobType->label()) ?></span><span><?= e($job->experienceLevel->label()) ?> level</span><span><?= e($job->categoryName) ?></span></div>
  <p class="posted"><?= e(Format::posted($job->publishedAt)) ?></p>
</li>
