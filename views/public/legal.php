<?php
/** @var string $slug */
/** @var string $title */
/** @var \DateTimeImmutable $updated */
/** @var string $summary */
/** @var list<array{id: string, heading: string, paragraphs: list<string>, items: list<string>, after: list<string>, table: array{head: list<string>, rows: list<list<string>>}|null}> $sections */
/** @var array<string, string> $pages */
/** @var list<string> $jsonLd */
/** @var string $appUrl */
use App\Core\Format;

$this->layout('layouts/public');
$this->set('pageTitle', $title);
$this->set('metaDescription', Format::excerpt($summary));
$this->set('canonical', $appUrl . '/' . $slug);
$this->start('head');
foreach ($jsonLd as $json): ?>
  <script type="application/ld+json"><?= $json ?></script>
<?php endforeach;
$this->end();
?>
<main class="container legal-page" id="main">
  <p class="crumbs"><a href="/">All jobs</a> › <?= e($title) ?></p>
  <h1><?= e($title) ?></h1>
  <p class="muted">Last updated <time datetime="<?= e($updated->format('Y-m-d')) ?>"><?= e(Format::date($updated, 'j F Y')) ?></time></p>

  <p class="legal-summary"><?= e($summary) ?></p>

  <nav class="legal-toc panel-card" aria-label="On this page">
    <p class="legal-toc-title">On this page</p>
    <ol>
      <?php foreach ($sections as $section): ?>
        <li><a href="#<?= e($section['id']) ?>"><?= e($section['heading']) ?></a></li>
      <?php endforeach; ?>
    </ol>
  </nav>

  <?php foreach ($sections as $i => $section): ?>
    <section class="legal-section" id="<?= e($section['id']) ?>">
      <h2><?= e(($i + 1) . '. ' . $section['heading']) ?></h2>
      <?php foreach ($section['paragraphs'] as $paragraph): ?>
        <p><?= e($paragraph) ?></p>
      <?php endforeach; ?>
      <?php if ($section['items'] !== []): ?>
        <ul><?php foreach ($section['items'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
      <?php if ($section['table'] !== null): ?>
        <div class="legal-table">
          <table>
            <thead><tr><?php foreach ($section['table']['head'] as $cell): ?><th scope="col"><?= e($cell) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
              <?php foreach ($section['table']['rows'] as $row): ?>
                <tr><?php foreach ($row as $j => $cell): ?><?= $j === 0 ? '<th scope="row">' : '<td>' ?><?= e($cell) ?><?= $j === 0 ? '</th>' : '</td>' ?><?php endforeach; ?></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
      <?php foreach ($section['after'] as $paragraph): ?>
        <p><?= e($paragraph) ?></p>
      <?php endforeach; ?>
    </section>
  <?php endforeach; ?>

  <nav class="legal-related" aria-label="Legal pages">
    <?php foreach ($pages as $pageSlug => $pageTitle): ?>
      <?php if ($pageSlug !== $slug): ?><a href="/<?= e($pageSlug) ?>"><?= e($pageTitle) ?></a><?php endif; ?>
    <?php endforeach; ?>
    <a href="/contact">Contact us</a>
  </nav>
</main>
