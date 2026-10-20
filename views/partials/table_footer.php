<?php
/** @var \App\Domain\Pagination $pagination */
/** @var \App\Domain\TableQuery $table */
/** @var string $path */
/** @var array<string, mixed> $filters */
/** @var string $noun e.g. "jobs" */
use App\Domain\TableQuery;

$hidden = array_filter([...$filters, 'sort' => $table->toQuery()['sort'] ?? null, 'dir' => $table->toQuery()['dir'] ?? null], static fn ($v) => $v !== null && $v !== '' && $v !== 0);
?>
<div class="table-footer">
  <p class="muted small" aria-live="polite">
    <?php if ($pagination->total === 0): ?>
      No <?= e($noun) ?>
    <?php else: ?>
      Showing <strong><?= e($pagination->from()) ?>–<?= e($pagination->to()) ?></strong> of <strong><?= e($pagination->total) ?></strong> <?= e($pagination->total === 1 ? ($nounOne ?? rtrim($noun, 's')) : $noun) ?>
    <?php endif; ?>
  </p>
  <form method="get" action="<?= e($path) ?>" class="per-page">
    <?php foreach ($hidden as $name => $value): ?>
      <input type="hidden" name="<?= e($name) ?>" value="<?= e($value) ?>">
    <?php endforeach; ?>
    <label class="muted small">Rows per page
      <select name="per_page" data-autosubmit>
        <?php foreach (TableQuery::PER_PAGE_OPTIONS as $option): ?>
          <option value="<?= e($option) ?>"<?= $option === $table->perPage ? ' selected' : '' ?>><?= e($option) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <noscript><button class="btn-ghost btn-sm" type="submit">Apply</button></noscript>
  </form>
  <?= $this->insert('partials/admin_pager', [
      'pagination' => $pagination,
      'path' => $path,
      'query' => array_filter([...$filters, ...$table->toQuery(['page' => null])], static fn ($v) => $v !== null && $v !== '' && $v !== 0),
  ]) ?>
</div>
