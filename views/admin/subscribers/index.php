<?php
/** @var \App\Domain\Paginated<\App\Domain\Subscriber> $result */
/** @var array{q: string, status: string} $filters */
/** @var array<string, int> $counts */
use App\Core\Format;
use App\Domain\SubscriberStatus;

$this->layout('layouts/admin');
$this->set('title', 'Subscribers');
/** @var \App\Domain\TableQuery $table */
$exportQuery = $filters['status'] !== '' ? '?status=' . rawurlencode($filters['status']) : '';
$filterQuery = array_filter($filters, static fn ($v) => $v !== '');
$sort = fn (string $key, string $label, string $first = 'asc'): string => $this->insert('partials/sort_th', [
    'table' => $table, 'key' => $key, 'label' => $label, 'path' => '/admin/subscribers', 'filters' => $filterQuery, 'first' => $first,
]);
?>
<header class="page-head">
  <h1>Subscribers</h1>
  <div class="head-actions">
    <a class="btn-ghost" href="/admin/subscribers/export.csv<?= e($exportQuery) ?>">Export CSV<?= $filters['status'] !== '' ? ' (' . e($filters['status']) . ')' : '' ?></a>
    <a class="btn-primary" href="/admin/subscribers/import">Import CSV</a>
  </div>
</header>

<div class="stats">
  <?php foreach (SubscriberStatus::cases() as $status): ?>
    <a class="stat stat-link<?= $filters['status'] === $status->value ? ' on' : '' ?>" href="/admin/subscribers?status=<?= e($status->value) ?>">
      <span><?= e($status->label()) ?></span><strong><?= e($counts[$status->value] ?? 0) ?></strong>
    </a>
  <?php endforeach; ?>
</div>

<form class="toolbar" method="get" action="/admin/subscribers" role="search">
  <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search email or name…" aria-label="Search subscribers">
  <select name="status" aria-label="Filter by status">
    <option value="">All statuses</option>
    <?php foreach (SubscriberStatus::cases() as $status): ?>
      <option value="<?= e($status->value) ?>"<?= $filters['status'] === $status->value ? ' selected' : '' ?>><?= e($status->label()) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($table->perPage !== 25): ?><input type="hidden" name="per_page" value="<?= e($table->perPage) ?>"><?php endif; ?>
  <button class="btn-ghost" type="submit">Search</button>
  <?php if ($filterQuery !== []): ?><a class="link-reset" href="/admin/subscribers">Clear</a><?php endif; ?>
</form>

<div class="table-wrap">
  <table>
    <thead><tr><?= $sort('email', 'Email') ?><th>Name</th><?= $sort('status', 'Status') ?><th>Consent</th><?= $sort('joined', 'Joined', 'desc') ?><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php if ($result->items === []): ?>
      <tr><td colspan="6" class="muted center">No subscribers match.</td></tr>
    <?php endif; ?>
    <?php foreach ($result->items as $s): ?>
      <tr>
        <td><strong><?= e($s->email) ?></strong></td>
        <td><?= e($s->name ?? '') ?></td>
        <td><span class="pill <?= e($s->status->value) ?>"><?= e($s->status->label()) ?></span></td>
        <td class="small"><?= e(Format::dateTime($s->consentAt)) ?><?php if ($s->consentIp !== null): ?><div class="muted"><?= e($s->consentIp) ?></div><?php endif; ?></td>
        <td class="small"><?= e(Format::date($s->createdAt)) ?></td>
        <td class="row-actions">
          <?php if ($s->status !== SubscriberStatus::Unsubscribed): ?>
            <form class="inline" method="post" action="/admin/subscribers/<?= e($s->id) ?>/unsubscribe" data-confirm="Unsubscribe <?= e($s->email) ?>?">
              <?= $this->insert('partials/csrf') ?>
              <button type="submit">Unsubscribe</button>
            </form>
          <?php endif; ?>
          <form class="inline" method="post" action="/admin/subscribers/<?= e($s->id) ?>/delete" data-confirm="Permanently delete <?= e($s->email) ?> and their delivery history? Use this for privacy requests.">
            <?= $this->insert('partials/csrf') ?>
            <button type="submit" class="danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?= $this->insert('partials/table_footer', ['pagination' => $result->pagination, 'table' => $table, 'path' => '/admin/subscribers', 'filters' => $filterQuery, 'noun' => 'subscribers']) ?>
