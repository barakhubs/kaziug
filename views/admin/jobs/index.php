<?php
/** @var \App\Domain\Paginated<\App\Domain\Job> $result */
/** @var \App\Domain\TableQuery $table */
/** @var array{q: string, status: string, category: int} $filters */
/** @var list<\App\Domain\Category> $categories */
/** @var array<string, int> $statusCounts */
use App\Core\Format;
use App\Domain\JobStatus;

$this->layout('layouts/admin');
$this->set('title', 'Jobs');
$filterQuery = array_filter($filters, static fn ($v) => $v !== '' && $v !== 0);
$currentQuery = http_build_query([...$filterQuery, ...$table->toQuery()]);
$returnTo = '/admin/jobs' . ($currentQuery !== '' ? '?' . $currentQuery : '');
$sort = fn (string $key, string $label, string $first = 'asc'): string => $this->insert('partials/sort_th', [
    'table' => $table, 'key' => $key, 'label' => $label, 'path' => '/admin/jobs', 'filters' => $filterQuery, 'first' => $first,
]);
?>
<header class="page-head">
  <h1>Jobs</h1>
  <div class="head-actions">
    <a class="btn-primary" href="/admin/jobs/new">+ New job</a>
  </div>
</header>

<form class="toolbar" method="get" action="/admin/jobs" role="search">
  <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search title, company, location…" aria-label="Search jobs">
  <select name="status" aria-label="Filter by status" data-autosubmit>
    <option value="">All statuses</option>
    <?php foreach (JobStatus::cases() as $status): ?>
      <option value="<?= e($status->value) ?>"<?= $filters['status'] === $status->value ? ' selected' : '' ?>>
        <?= e($status->label()) ?> (<?= e($statusCounts[$status->value] ?? 0) ?>)
      </option>
    <?php endforeach; ?>
  </select>
  <select name="category" aria-label="Filter by category" data-autosubmit>
    <option value="">All categories</option>
    <?php foreach ($categories as $category): ?>
      <option value="<?= e($category->id) ?>"<?= $filters['category'] === $category->id ? ' selected' : '' ?>><?= e($category->name) ?></option>
    <?php endforeach; ?>
  </select>
  <?php if ($table->perPage !== 20): ?><input type="hidden" name="per_page" value="<?= e($table->perPage) ?>"><?php endif; ?>
  <button class="btn-ghost" type="submit">Search</button>
  <?php if ($filterQuery !== []): ?><a class="link-reset" href="/admin/jobs">Clear</a><?php endif; ?>
</form>

<form id="bulk" class="toolbar" method="post" action="/admin/jobs/bulk-status">
  <?= $this->insert('partials/csrf') ?>
  <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
  <label class="muted" for="bulk-to">With selected:</label>
  <select id="bulk-to" name="to">
    <?php foreach (JobStatus::cases() as $status): ?>
      <option value="<?= e($status->value) ?>">Move to <?= e(strtolower($status->label())) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn-ghost btn-sm" type="submit">Apply</button>
</form>

<div class="table-wrap">
  <table>
    <thead>
      <tr>
        <th><input type="checkbox" data-check-all="ids[]" aria-label="Select all jobs on this page"></th>
        <?= $sort('title', 'Job') ?>
        <?= $sort('status', 'Status') ?>
        <?= $sort('posted', 'Posted', 'desc') ?>
        <th><span class="sr-only">Actions</span></th>
      </tr>
    </thead>
    <tbody>
    <?php if ($result->items === []): ?>
      <tr><td colspan="5" class="muted center">No jobs match these filters.</td></tr>
    <?php endif; ?>
    <?php foreach ($result->items as $job): ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= e($job->id) ?>" form="bulk" aria-label="Select <?= e($job->title) ?>"></td>
        <td>
          <strong><a href="/admin/jobs/<?= e($job->id) ?>"><?= e($job->title) ?></a></strong>
          <div class="sub"><?= e($job->company) ?> · <?= e($job->categoryName) ?><?= $job->isExpired() ? ' · expired' : '' ?></div>
        </td>
        <td><span class="pill <?= e($job->status->value) ?>"><?= e($job->status->label()) ?></span></td>
        <td class="small nowrap"><?= e($job->publishedAt !== null ? Format::date($job->publishedAt) : '—') ?></td>
        <td class="row-actions">
          <?php if ($job->status->allowedNext() !== []): ?>
            <form class="status-form" method="post" action="/admin/jobs/<?= e($job->id) ?>/status">
              <?= $this->insert('partials/csrf') ?>
              <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
              <select name="to" aria-label="Change status of <?= e($job->title) ?>">
                <?php foreach ($job->status->allowedNext() as $next): ?>
                  <option value="<?= e($next->value) ?>"><?= e($job->status->actionLabel($next)) ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit">Go</button>
            </form>
          <?php endif; ?>
          <a class="row-link" href="/admin/jobs/<?= e($job->id) ?>">View</a>
          <a class="row-link" href="/admin/jobs/<?= e($job->id) ?>/edit">Edit</a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?= $this->insert('partials/table_footer', ['pagination' => $result->pagination, 'table' => $table, 'path' => '/admin/jobs', 'filters' => $filterQuery, 'noun' => 'jobs']) ?>
