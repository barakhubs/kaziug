<?php
/** @var string $title */
/** @var list<\App\Domain\AnalyticsRow> $rows */
/** @var array<string, string> $columns metric key => heading: views, visitors, applies, rate, searches, zero */
/** @var string $first heading of the first column */
/** @var string $exportTable */
/** @var array<string, string> $rangeQuery */
/** @var string $empty */
/** @var string|null $barMetric metric the row bars show (default pageviews) */
use App\Controllers\Admin\AnalyticsPresenter;

$max = max([1, ...array_map(static fn ($row) => $row->metrics->{$barMetric ?? 'pageviews'}, $rows)]);
?>
<section class="panel">
  <div class="panel-head">
    <h2><?= e($title) ?></h2>
    <a href="/admin/analytics/export.csv?<?= e(http_build_query(['table' => $exportTable, ...$rangeQuery])) ?>">CSV</a>
  </div>
  <?php if ($rows === []): ?>
    <p class="muted"><?= e($empty) ?></p>
  <?php else: ?>
    <div class="table-wrap">
      <table class="analytics-table">
        <thead><tr><th><?= e($first) ?></th><?php foreach ($columns as $heading): ?><th class="num"><?= e($heading) ?></th><?php endforeach; ?></tr></thead>
        <tbody>
          <?php foreach ($rows as $row): $m = $row->metrics; ?>
            <tr>
              <td>
                <?php if ($row->href !== null): ?><a href="<?= e($row->href) ?>"><?= e($row->label) ?></a><?php else: ?><?= e($row->label) ?><?php endif; ?>
                <?php if ($row->note !== null && $row->note !== 'published'): ?> <span class="pill <?= e($row->note === 'deleted' ? 'failed' : $row->note) ?>"><?= e(ucfirst($row->note)) ?></span><?php endif; ?>
                <meter class="bar row-bar" min="0" max="<?= e($max) ?>" value="<?= e($m->{$barMetric ?? 'pageviews'}) ?>" aria-hidden="true"></meter>
              </td>
              <?php foreach (array_keys($columns) as $key): ?>
                <td class="num"><?= e(match ($key) {
                    'views' => number_format($m->pageviews),
                    'visitors' => number_format($m->visitors),
                    'applies' => number_format($m->applies),
                    'rate' => AnalyticsPresenter::percent($m->applyRate()),
                    'searches' => number_format($m->searches),
                    'zero' => number_format($m->zeroResults),
                    default => '',
                }) ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
