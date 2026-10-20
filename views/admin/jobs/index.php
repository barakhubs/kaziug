<?php
/** @var \App\Domain\Paginated<\App\Domain\Job> $result */
/** @var array{q: string, status: string, category_id: int} $filters */
/** @var list<\App\Domain\Category> $categories */
/** @var array<string, int> $statusCounts */
/** @var string $currentPath */
use App\Core\Format;
use App\Domain\JobStatus;

$this->layout('layouts/admin');
$this->set('title', 'Jobs');
$query = ['q' => $filters['q'], 'status' => $filters['status'], 'category' => $filters['category_id'] ?: null];
$returnTo = '/admin/jobs' . (($qs = http_build_query(array_filter([...$query, 'page' => $result->pagination->page > 1 ? $result->pagination->page : null]))) !== '' ? '?' . $qs : '');
?>
<header class="page-head">
  <h1>Jobs</h1>
  <div class="head-actions">
    <a class="btn-primary" href="/admin/jobs/new">+ New job</a>
  </div>
</header>

<form class="toolbar" method="get" action="/admin/jobs" role="search">
  <input type="search" name="q" value="<?= e($filters['q']) ?>" placeholder="Search title, company, location…" aria-label="Search jobs">
  <select name="status" aria-label="Filter by status">
    <option value="">All statuses</option>
    <?php foreach (JobStatus::cases() as $status): ?>
      <option value="<?= e($status->value) ?>"<?= $filters['status'] === $status->value ? ' selected' : '' ?>>
        <?= e($status->label()) ?> (<?= e($statusCounts[$status->value] ?? 0) ?>)
      </option>
    <?php endforeach; ?>
  </select>
  <select name="category" aria-label="Filter by category">
    <option value="">All categories</option>
    <?php foreach ($categories as $category): ?>
      <option value="<?= e($category->id) ?>"<?= $filters['category_id'] === $category->id ? ' selected' : '' ?>><?= e($category->name) ?></option>
    <?php endforeach; ?>
  </select>
  <button class="btn-ghost" type="submit">Filter</button>
  <span class="muted"><?= e($result->pagination->total) ?> job(s)</span>
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
        <th>Title</th><th>Company</th><th>Location</th><th>Type</th><th>Status</th><th>Updated</th>
        <th><span class="sr-only">Actions</span></th>
      </tr>
    </thead>
    <tbody>
    <?php if ($result->items === []): ?>
      <tr><td colspan="8" class="muted center">No jobs match these filters.</td></tr>
    <?php endif; ?>
    <?php foreach ($result->items as $job): ?>
      <tr>
        <td><input type="checkbox" name="ids[]" value="<?= e($job->id) ?>" form="bulk" aria-label="Select <?= e($job->title) ?>"></td>
        <td>
          <strong><a href="/admin/jobs/<?= e($job->id) ?>/edit"><?= e($job->title) ?></a></strong>
          <div class="muted small"><?= e($job->categoryName) ?><?= $job->isExpired() ? ' · expired' : '' ?></div>
        </td>
        <td><?= e($job->company) ?></td>
        <td><?= e($job->location) ?><?= $job->isRemote ? ' · Remote' : '' ?></td>
        <td><?= e($job->jobType->label()) ?></td>
        <td><span class="pill <?= e($job->status->value) ?>"><?= e($job->status->label()) ?></span></td>
        <td class="small"><?= e(Format::date($job->updatedAt)) ?></td>
        <td class="row-actions">
          <?php if ($job->status->allowedNext() !== []): ?>
            <form class="inline" method="post" action="/admin/jobs/<?= e($job->id) ?>/status">
              <?= $this->insert('partials/csrf') ?>
              <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
              <?php foreach ($job->status->allowedNext() as $next): ?>
                <button type="submit" name="to" value="<?= e($next->value) ?>"><?= e($job->status->actionLabel($next)) ?></button>
              <?php endforeach; ?>
            </form>
          <?php endif; ?>
          <a class="row-link" href="/admin/jobs/<?= e($job->id) ?>/edit">Edit</a>
          <form class="inline" method="post" action="/admin/jobs/<?= e($job->id) ?>/duplicate">
            <?= $this->insert('partials/csrf') ?>
            <button type="submit">Duplicate</button>
          </form>
          <form class="inline" method="post" action="/admin/jobs/<?= e($job->id) ?>/delete" data-confirm="Delete “<?= e($job->title) ?>”? It will disappear from the site and the back office.">
            <?= $this->insert('partials/csrf') ?>
            <input type="hidden" name="return_to" value="<?= e($returnTo) ?>">
            <button type="submit" class="danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?= $this->insert('partials/admin_pager', ['pagination' => $result->pagination, 'path' => '/admin/jobs', 'query' => $query]) ?>
