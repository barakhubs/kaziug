<?php
/** @var \App\Controllers\Admin\LineChart $chart */
/** @var string $id */
$marks = $chart->marks();
?>
<figure class="chart" data-chart>
  <figcaption class="chart-head">
    <h2 id="<?= e($id) ?>-title"><?= e($chart->title) ?></h2>
    <span class="muted small"><?= e(number_format($chart->total())) ?> in total</span>
  </figcaption>
  <div class="chart-plot">
    <svg viewBox="0 0 <?= e($chart->width) ?> <?= e($chart->height) ?>" role="img" aria-labelledby="<?= e($id) ?>-title">
      <?php foreach ($chart->yTicks() as $tick): ?>
        <line class="chart-grid" x1="<?= e($chart->left()) ?>" x2="<?= e($chart->right()) ?>" y1="<?= e($tick['y']) ?>" y2="<?= e($tick['y']) ?>"/>
        <text class="chart-axis" x="<?= e($chart->left() - 8) ?>" y="<?= e($tick['y'] + 4) ?>" text-anchor="end"><?= e($tick['label']) ?></text>
      <?php endforeach; ?>
      <?php foreach ($chart->xTicks() as $tick): ?>
        <text class="chart-axis" x="<?= e($tick['x']) ?>" y="<?= e($chart->height - 6) ?>" text-anchor="middle"><?= e($tick['label']) ?></text>
      <?php endforeach; ?>
      <path class="chart-area" d="<?= e($chart->areaPath()) ?>"/>
      <path class="chart-line" d="<?= e($chart->linePath()) ?>"/>
      <line class="chart-crosshair" x1="0" x2="0" y1="<?= e($chart->baseline()) ?>" y2="12" hidden/>
      <circle class="chart-dot" r="4.5" cx="0" cy="0" hidden/>
      <?php foreach ($marks as $mark): ?>
        <circle class="chart-point" cx="<?= e($mark['x']) ?>" cy="<?= e($mark['y']) ?>" r="0" data-label="<?= e($mark['label']) ?>" data-value="<?= e(number_format($mark['value'])) ?>"/>
      <?php endforeach; ?>
    </svg>
    <div class="chart-tip" role="status" hidden><strong></strong><span></span></div>
  </div>
</figure>
