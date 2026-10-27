<?php
/** @var \App\Domain\DateRange $range */
/** @var array<string, mixed> $report */
/** @var list<array{label: string, value: string, delta: array{text: string, dir: string}|null, hint: string}> $tiles */
/** @var \App\Controllers\Admin\LineChart $visitorsChart */
/** @var \App\Controllers\Admin\LineChart $appliesChart */
use App\Controllers\Admin\AnalyticsPresenter;
use App\Domain\DateRange;

$this->layout('layouts/admin');
$this->set('title', 'Analytics');
$rangeQuery = $range->query();
$tables = $report['tables'];
$subs = $report['subscribers'];
$table = fn (string $name, string $title, string $first, array $columns, string $empty, string $barMetric = 'pageviews'): string => $this->insert('partials/analytics_table', [
    'title' => $title, 'rows' => $tables[$name], 'first' => $first, 'columns' => $columns, 'empty' => $empty,
    'exportTable' => $name, 'rangeQuery' => $rangeQuery, 'barMetric' => $barMetric,
]);
?>
<header class="page-head">
  <div>
    <h1>Analytics</h1>
    <p class="muted small range-note"><?= e($range->from->format('j M Y')) ?> – <?= e($range->to->format('j M Y')) ?>, compared with the <?= e($range->days()) ?> days before</p>
  </div>
  <a class="btn-ghost" href="/admin/analytics/export.csv?<?= e(http_build_query(['table' => 'daily', ...$rangeQuery])) ?>">Export daily CSV</a>
</header>

<form class="toolbar range-bar" method="get" action="/admin/analytics">
  <nav class="segmented" aria-label="Date range">
    <?php foreach (DateRange::PRESETS as $days): ?>
      <a href="/admin/analytics?range=<?= e($days) ?>" class="<?= $range->preset === $days ? 'on' : '' ?>"<?= $range->preset === $days ? ' aria-current="true"' : '' ?>><?= e($days === 365 ? '12 months' : "{$days} days") ?></a>
    <?php endforeach; ?>
  </nav>
  <label class="muted small">From <input type="date" name="from" value="<?= e($range->from->format('Y-m-d')) ?>" required></label>
  <label class="muted small">To <input type="date" name="to" value="<?= e($range->to->format('Y-m-d')) ?>" required></label>
  <button class="btn-ghost btn-sm" type="submit">Apply</button>
</form>

<div class="stats kpis">
  <?php foreach ($tiles as $tile): ?>
    <div class="stat" title="<?= e($tile['hint']) ?>">
      <span><?= e($tile['label']) ?></span>
      <strong><?= e($tile['value']) ?></strong>
      <?php if ($tile['delta'] !== null): ?>
        <span class="delta <?= e($tile['delta']['dir']) ?>"><?= e($tile['delta']['text']) ?> <span class="muted">vs previous</span></span>
      <?php else: ?>
        <span class="delta flat">No earlier data</span>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="analytics-charts">
  <section class="panel"><?= $this->insert('partials/line_chart', ['chart' => $visitorsChart, 'id' => 'visitors-chart']) ?></section>
  <section class="panel"><?= $this->insert('partials/line_chart', ['chart' => $appliesChart, 'id' => 'applies-chart']) ?></section>
</div>
<details class="panel table-view">
  <summary>Daily figures as a table</summary>
  <div class="table-wrap">
    <table class="analytics-table">
      <thead><tr><th>Day</th><th class="num">Visitors</th><th class="num">Page views</th><th class="num">Apply clicks</th><th class="num">Searches</th></tr></thead>
      <tbody>
        <?php foreach (array_reverse($report['series']) as $point): $m = $point['metrics']; ?>
          <tr><td><?= e($point['day']->format('D j M Y')) ?></td><td class="num"><?= e(number_format($m->visitors)) ?></td><td class="num"><?= e(number_format($m->pageviews)) ?></td><td class="num"><?= e(number_format($m->applies)) ?></td><td class="num"><?= e(number_format($m->searches)) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</details>

<div class="analytics-grid">
  <div class="span-2"><?= $table('jobs', 'Top jobs', 'Job', ['views' => 'Views', 'applies' => 'Apply clicks', 'rate' => 'Apply rate'], 'No job views in this period.') ?></div>
  <?= $table('categories', 'Categories', 'Category', ['views' => 'Views', 'applies' => 'Apply clicks'], 'No category activity in this period.') ?>
  <?= $table('pages', 'Top pages', 'Page', ['views' => 'Views', 'visitors' => 'Visitors'], 'No page views in this period.') ?>
  <?= $table('referrers', 'Referring sites', 'Site', ['views' => 'Views', 'visitors' => 'Visitors'], 'No visits from other sites in this period.') ?>
  <?= $table('sources', 'Campaign sources (utm_source)', 'Source', ['views' => 'Views', 'visitors' => 'Visitors'], 'No tagged links used in this period. Add ?utm_source=… to links you share.') ?>
  <?= $table('campaigns', 'Campaigns (utm_campaign)', 'Campaign', ['views' => 'Views', 'visitors' => 'Visitors'], 'No campaigns in this period.') ?>
  <?= $table('page_types', 'Page types', 'Type', ['views' => 'Views', 'visitors' => 'Visitors'], 'No page views in this period.') ?>
  <?= $table('devices', 'Devices', 'Device', ['views' => 'Views', 'visitors' => 'Visitors'], 'No page views in this period.') ?>
  <?= $table('channels', 'How people apply', 'Channel', ['applies' => 'Apply clicks'], 'No apply clicks in this period.', 'applies') ?>
  <?= $table('searches', 'Top searches', 'Search term', ['searches' => 'Searches', 'zero' => 'No results'], 'No searches in this period.', 'searches') ?>
  <?= $table('zero_searches', 'Searches with no results', 'Search term', ['zero' => 'Times'], 'Every search found something.', 'zeroResults') ?>
  <?= $table('filters', 'Filters used', 'Filter', ['searches' => 'Searches'], 'No filters used in this period.', 'searches') ?>

  <section class="panel">
    <h2>Job alert sign-ups</h2>
    <dl class="kv funnel">
      <dt>Signed up on the site</dt><dd><?= e(number_format($subs['signups'])) ?></dd>
      <dt>Confirmed their email</dt><dd><?= e(number_format($subs['confirmations'])) ?></dd>
      <dt>Unsubscribed</dt><dd><?= e(number_format($subs['unsubscribes'])) ?></dd>
    </dl>
    <p class="hint">Counted by when each step happened. Imported subscribers are not included.</p>
  </section>
</div>

<p class="hint section-gap">Counted without cookies: each visitor gets an anonymous ID that changes every day, so “visitors” are unique per day and added up over the period. Bots, visitors who ask not to be tracked (Do Not Track / Global Privacy Control) and your own visits while signed in are left out. Raw records are kept for a year; daily totals are kept after that.</p>
