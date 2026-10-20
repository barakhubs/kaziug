<?php
/** @var list<array<string, mixed>> $rows */
/** @var \App\Domain\Pagination $pagination */
/** @var \App\Domain\TableQuery $table */
/** @var array{action: string, entity: string, actor: string, from: string, to: string} $filters */
/** @var array{actions: list<string>, entities: list<string>, actors: list<string>} $options */
$this->layout('layouts/admin');
$this->set('title', 'Audit log');
$filterQuery = array_filter($filters, static fn ($v) => $v !== '');
$select = static function (string $name, string $label, array $values, string $current): string {
    $html = '<select name="' . e($name) . '" aria-label="' . e($label) . '" data-autosubmit><option value="">' . e($label) . ': all</option>';
    foreach ($values as $value) {
        $html .= '<option value="' . e($value) . '"' . ($value === $current ? ' selected' : '') . '>' . e($value) . '</option>';
    }

    return $html . '</select>';
};
?>
<header class="page-head"><h1>Audit log</h1></header>

<form class="toolbar" method="get" action="/admin/audit">
  <?= $select('action', 'Action', $options['actions'], $filters['action']) ?>
  <?= $select('entity', 'Entity', $options['entities'], $filters['entity']) ?>
  <?= $select('actor', 'Actor', $options['actors'], $filters['actor']) ?>
  <label class="muted small">From <input type="date" name="from" value="<?= e($filters['from']) ?>"></label>
  <label class="muted small">To <input type="date" name="to" value="<?= e($filters['to']) ?>"></label>
  <?php if ($table->perPage !== 50): ?><input type="hidden" name="per_page" value="<?= e($table->perPage) ?>"><?php endif; ?>
  <button class="btn-ghost" type="submit">Filter</button>
  <?php if ($filterQuery !== []): ?><a class="link-reset" href="/admin/audit">Clear</a><?php endif; ?>
</form>

<?= $this->insert('partials/audit_rows', [
    'rows' => $rows,
    'whenTh' => $this->insert('partials/sort_th', ['table' => $table, 'key' => 'when', 'label' => 'When', 'path' => '/admin/audit', 'filters' => $filterQuery, 'first' => 'desc']),
]) ?>
<?= $this->insert('partials/table_footer', ['pagination' => $pagination, 'table' => $table, 'path' => '/admin/audit', 'filters' => $filterQuery, 'noun' => 'entries', 'nounOne' => 'entry']) ?>
