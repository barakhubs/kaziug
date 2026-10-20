<?php
/** @var \App\Domain\Job $job */
/** @var string $jsonLd */
/** @var string $appUrl */
/** @var \App\Domain\SiteSettings $site */
use App\Core\Format;

$this->layout('layouts/public');
$url = $appUrl . '/jobs/' . $job->slug;
$description = mb_substr($job->summary, 0, 300);
$this->set('pageTitle', $job->title . ' at ' . $job->company);
$this->set('metaDescription', $description);
$this->set('canonical', $url);
$this->start('head');
?>
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= e($site->siteName) ?>">
  <meta property="og:title" content="<?= e($job->title . ' at ' . $job->company) ?>">
  <meta property="og:description" content="<?= e($description) ?>">
  <meta property="og:url" content="<?= e($url) ?>">
  <meta name="twitter:card" content="summary">
  <script type="application/ld+json"><?= $jsonLd ?></script>
<?php $this->end(); ?>
<main class="container job-page" id="main">
  <p class="crumbs">
    <a href="/">All jobs</a> › <a href="/categories/<?= e($job->categorySlug) ?>"><?= e($job->categoryName) ?></a>
  </p>
  <article class="detail detail-page">
    <?= $this->insert('partials/job_detail', ['job' => $job, 'inPane' => false]) ?>
    <p class="muted small-print">Posted on <?= e(Format::date($job->publishedAt)) ?> · Job ID <?= e($job->id) ?></p>
  </article>
</main>
