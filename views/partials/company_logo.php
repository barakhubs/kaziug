<?php
/** @var \App\Domain\Job $job */
/** @var string $size "sm" on job cards, "lg" on job pages */
// Decorative: the company name is always printed next to it.
use App\Core\Format;

$logoUrl = $job->companyLogoUrl();
?>
<?php if ($logoUrl !== null): ?>
  <img class="company-logo <?= e($size) ?>" src="<?= e($logoUrl) ?>" alt="" loading="lazy" decoding="async">
<?php else: ?>
  <span class="company-logo initials <?= e($size) ?>" aria-hidden="true"><?= e(Format::initials($job->company)) ?></span>
<?php endif; ?>
