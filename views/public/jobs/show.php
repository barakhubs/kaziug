<?php
/** @var \App\Domain\Job $job */
/** @var string $jsonLd */
/** @var string $breadcrumbsJsonLd */
/** @var string $citySlug */
/** @var string $appUrl */
/** @var \App\Domain\SiteSettings $site */
use App\Core\Format;

$this->layout('layouts/public');
$this->set('pageTitle', $job->title . ' at ' . $job->company . ', ' . $job->location);
$this->set('metaDescription', Format::excerpt(Format::jobFacts($job) . ' ' . $job->summary));
$this->set('canonical', $appUrl . '/jobs/' . $job->slug);
$this->start('head');
?>
  <script type="application/ld+json"><?= $jsonLd ?></script>
  <script type="application/ld+json"><?= $breadcrumbsJsonLd ?></script>
<?php $this->end(); ?>
<main class="container job-page" id="main">
  <p class="crumbs">
    <a href="/">All jobs</a> › <a href="/categories/<?= e($job->categorySlug) ?>"><?= e($job->categoryName) ?></a> › <?= e($job->title) ?>
  </p>
  <article class="detail detail-page">
    <?= $this->insert('partials/job_detail', ['job' => $job, 'inPane' => false]) ?>
    <p class="job-facts"><?= e(Format::jobFacts($job)) ?></p>
    <p class="muted small-print">
      Posted <?= e(Format::date($job->publishedAt)) ?> · Last updated <?= e(Format::date($job->updatedAt)) ?>
      <?php if ($job->expiresAt !== null): ?> · Closes <?= e(Format::date($job->expiresAt)) ?><?php endif; ?>
      · Job ID <?= e($job->id) ?>
    </p>
    <p class="related-links">
      More: <a href="/jobs-in/<?= e($citySlug) ?>">jobs in <?= e($job->location) ?></a> ·
      <a href="/categories/<?= e($job->categorySlug) ?>"><?= e($job->categoryName) ?> jobs</a> ·
      <a href="/cv-help">get help with your CV</a>
    </p>
  </article>
</main>
