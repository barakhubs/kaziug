<?php
/** @var array<string, string> $errors */
/** @var array{imported: int, existing: int, duplicates_in_file: int, invalid: list<array{line: int, reason: string}>}|null $report */
use App\Services\Import\CsvSubscriberParser;

$this->layout('layouts/admin');
$this->set('title', 'Import subscribers');
$err = static fn (string $key): string => isset($errors[$key]) ? 'has-error' : '';
?>
<header class="page-head">
  <h1>Import subscribers</h1>
  <div class="head-actions"><a class="btn-ghost" href="/admin/subscribers">← All subscribers</a></div>
</header>

<?php if ($report !== null): ?>
  <div class="panel">
    <h2>Import finished</h2>
    <dl class="kv">
      <dt>Imported</dt><dd><strong><?= e($report['imported']) ?></strong></dd>
      <dt>Already subscribed (skipped)</dt><dd><?= e($report['existing']) ?></dd>
      <dt>Duplicates in the file</dt><dd><?= e($report['duplicates_in_file']) ?></dd>
      <dt>Invalid rows</dt><dd><?= e(count($report['invalid'])) ?></dd>
    </dl>
    <?php if ($report['invalid'] !== []): ?>
      <h3>Rows that were not imported</h3>
      <ul class="timeline">
        <?php foreach (array_slice($report['invalid'], 0, 50) as $row): ?>
          <li>Line <?= e($row['line']) ?>: <?= e($row['reason']) ?></li>
        <?php endforeach; ?>
        <?php if (count($report['invalid']) > 50): ?><li class="muted">…and <?= e(count($report['invalid']) - 50) ?> more.</li><?php endif; ?>
      </ul>
    <?php endif; ?>
  </div>
<?php endif; ?>

<form class="form-card section-gap" method="post" action="/admin/subscribers/import" enctype="multipart/form-data" novalidate>
  <h2>Upload a CSV file</h2>
  <p class="hint">One subscriber per row. Columns: <code>email</code> and optionally <code>name</code>, with or without a header row.
    Up to <?= e(number_format(CsvSubscriberParser::MAX_ROWS)) ?> rows and 1 MB. Addresses that are already on the list are skipped.</p>
  <?= $this->insert('partials/csrf') ?>
  <label class="<?= $err('csv') ?>">CSV file
    <input type="file" name="csv" accept=".csv,text/csv" required>
    <?= $this->insert('partials/field_error', ['field' => 'csv']) ?>
  </label>
  <label class="check <?= $err('consent') ?>">
    <input type="checkbox" name="consent" value="1" required>
    Everyone in this file has agreed to receive job alerts from us. They will be added as active subscribers.
  </label>
  <?= $this->insert('partials/field_error', ['field' => 'consent']) ?>
  <div class="form-actions"><button class="btn-primary" type="submit">Import</button></div>
</form>
