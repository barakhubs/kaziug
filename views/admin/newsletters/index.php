<?php
/** @var \App\Domain\Paginated<\App\Domain\Newsletter> $result */
/** @var array<int, \App\Domain\DeliveryCounts> $counts */
/** @var \App\Domain\TableQuery $table */
/** @var string $q */
use App\Core\Format;

$this->layout('layouts/admin');
$this->set('title', 'Newsletters');
$filterQuery = $q !== '' ? ['q' => $q] : [];
$sort = fn (string $key, string $label, string $first = 'asc'): string => $this->insert('partials/sort_th', [
    'table' => $table, 'key' => $key, 'label' => $label, 'path' => '/admin/newsletters', 'filters' => $filterQuery, 'first' => $first,
]);
?>
<header class="page-head">
  <h1>Newsletters</h1>
  <div class="head-actions"><a class="btn-primary" href="/admin/newsletters/new">+ New newsletter</a></div>
</header>

<form class="toolbar" method="get" action="/admin/newsletters" role="search">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search subjects…" aria-label="Search newsletters">
  <?php if ($table->perPage !== 20): ?><input type="hidden" name="per_page" value="<?= e($table->perPage) ?>"><?php endif; ?>
  <button class="btn-ghost" type="submit">Search</button>
  <?php if ($q !== ''): ?><a class="link-reset" href="/admin/newsletters">Clear</a><?php endif; ?>
</form>

<div class="table-wrap">
  <table>
    <thead><tr><?= $sort('subject', 'Subject') ?><?= $sort('status', 'Status') ?><?= $sort('created', 'When', 'desc') ?><th>Queued</th><th>Sent</th><th>Failed</th></tr></thead>
    <tbody>
    <?php if ($result->items === []): ?>
      <tr><td colspan="6" class="muted center"><?= $q !== '' ? 'No newsletters match.' : 'No newsletters yet.' ?></td></tr>
    <?php endif; ?>
    <?php foreach ($result->items as $n): $c = $counts[$n->id]; ?>
      <tr>
        <td><strong><a href="/admin/newsletters/<?= e($n->id) ?>"><?= e($n->subject) ?></a></strong>
          <?php if ($n->createdBy !== null): ?><div class="sub">by <?= e($n->createdBy) ?></div><?php endif; ?></td>
        <td><span class="pill <?= e($n->status->value) ?>"><?= e($n->status->label()) ?></span></td>
        <td class="small"><?= e(match (true) {
            $n->sentAt !== null => 'Sent ' . Format::dateTime($n->sentAt),
            $n->scheduledAt !== null => 'Scheduled ' . Format::dateTime($n->scheduledAt),
            default => 'Created ' . Format::date($n->createdAt),
        }) ?></td>
        <td><?= e($c->queued) ?></td>
        <td><?= e($c->sent) ?></td>
        <td><?= e($c->failed) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?= $this->insert('partials/table_footer', ['pagination' => $result->pagination, 'table' => $table, 'path' => '/admin/newsletters', 'filters' => $filterQuery, 'noun' => 'newsletters']) ?>
