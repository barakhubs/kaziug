<?php
/** @var \App\Domain\Job $job */
/** @var string $jsonLd */
/** @var string $breadcrumbsJsonLd */
/** @var string $citySlug */
/** @var list<\App\Domain\Job> $related */
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
  <div class="job-page-grid">
    <article class="detail detail-page">
      <?= $this->insert('partials/job_detail', ['job' => $job, 'inPane' => false]) ?>
      <p class="job-facts"><?= e(Format::jobFacts($job)) ?></p>
      <p class="muted small-print">
        Posted <?= e(Format::date($job->publishedAt)) ?> · Last updated <?= e(Format::date($job->updatedAt)) ?>
        <?php if ($job->expiresAt !== null): ?> · Closes <?= e(Format::date($job->expiresAt)) ?><?php endif; ?>
        · Job ID <?= e($job->id) ?>
      </p>
    </article>

    <aside class="job-sidebar" aria-label="More jobs">
      <?php if ($related !== []): ?>
        <section class="side-card">
          <h2>Related jobs</h2>
          <ul class="related-jobs">
            <?php foreach ($related as $other): ?>
              <li>
                <a class="related-title" href="/jobs/<?= e($other->slug) ?>"><?= e($other->title) ?></a>
                <span class="related-meta"><?= e($other->company) ?> · <?= e($other->location) ?><?= $other->isRemote ? ' · Remote' : '' ?></span>
                <span class="related-salary"><?= e(Format::salary($other->salaryMin, $other->salaryMax, $other->currency)) ?></span>
                <?php if ($other->expiresAt !== null): $dl = Format::deadline($other->expiresAt); ?>
                  <span class="deadline-pill <?= e($dl['state']) ?>"><?= e($dl['text']) ?></span>
                <?php endif; ?>
              </li>
            <?php endforeach; ?>
          </ul>
          <p class="side-links">
            <a href="/categories/<?= e($job->categorySlug) ?>">More <?= e($job->categoryName) ?> jobs →</a>
            <a href="/jobs-in/<?= e($citySlug) ?>">All jobs in <?= e($job->location) ?> →</a>
          </p>
        </section>
      <?php endif; ?>

      <section class="side-card cv-promo">
        <h2>Need help with your CV?</h2>
        <p>Get your CV reviewed and fixed so it stands out to employers, for <?= e(Format::money($site->cvServicePrice)) ?>.</p>
        <a class="btn-primary" href="/cv-help">Get CV help</a>
      </section>
    </aside>
  </div>
</main>
