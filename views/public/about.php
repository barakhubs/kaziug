<?php
/** @var string $intro */
/** @var list<array{heading: string, paragraphs: list<string>}> $sections */
/** @var array{jobs: int, cities: int, categories: int} $stats */
/** @var list<string> $jsonLd */
/** @var string $appUrl */
/** @var \App\Domain\SiteSettings $site */
use App\Core\Format;

$this->layout('layouts/public');
$this->set('pageTitle', 'About us');
$this->set('metaDescription', Format::excerpt($intro));
$this->set('canonical', $appUrl . '/about');
$this->start('head');
foreach ($jsonLd as $json): ?>
  <script type="application/ld+json"><?= $json ?></script>
<?php endforeach;
$this->end();
?>
<main class="container about-page" id="main">
  <p class="crumbs"><a href="/">All jobs</a> › About us</p>

  <section class="about-hero panel-card">
    <img src="<?= e($site->logoPath()) ?>" alt="" class="about-logo" width="200" height="72">
    <div>
      <h1>About <?= e($site->siteName) ?></h1>
      <p class="about-intro"><?= e($intro) ?></p>
    </div>
  </section>

  <ul class="about-stats" aria-label="<?= e($site->siteName) ?> today">
    <li><strong><?= e(number_format($stats['jobs'])) ?></strong><span>open job<?= $stats['jobs'] === 1 ? '' : 's' ?> right now</span></li>
    <li><strong><?= e(number_format($stats['cities'])) ?></strong><span>cit<?= $stats['cities'] === 1 ? 'y' : 'ies' ?> with vacancies</span></li>
    <li><strong><?= e(number_format($stats['categories'])) ?></strong><span>job categor<?= $stats['categories'] === 1 ? 'y' : 'ies' ?></span></li>
  </ul>

  <div class="about-sections">
    <?php foreach ($sections as $section): ?>
      <section class="panel-card">
        <h2><?= e($section['heading']) ?></h2>
        <?php foreach ($section['paragraphs'] as $paragraph): ?>
          <p><?= e($paragraph) ?></p>
        <?php endforeach; ?>
      </section>
    <?php endforeach; ?>
  </div>

  <section class="about-cta panel-card">
    <h2>Get started</h2>
    <div class="actions">
      <a class="btn-primary" href="/">Browse jobs</a>
      <a class="btn-ghost" href="/cv-help">Get CV help</a>
      <a class="btn-ghost" href="/contact">Contact us</a>
    </div>
  </section>
</main>
