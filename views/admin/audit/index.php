<?php
/** @var list<array<string, mixed>> $rows */
/** @var \App\Domain\Pagination $pagination */
/** @var array{action: string, entity: string, actor: string, from: string, to: string} $filters */
/** @var array{actions: list<string>, entities: list<string>, actors: list<string>} $options */
$this->layout('layouts/admin');
$this->set('title', 'Audit log');
$select = static function (string $name, string $label, array $values, string $current): string {
    $html = '<select name="' . e($name) . '" aria-label="' . e($label) . '"><option value="">' . e($label) . ': all</option>';
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
  <button class="btn-ghost" type="submit">Filter</button>
  <a class="link-reset" href="/admin/audit">Reset</a>
  <span class="muted"><?= e($pagination->total) ?> entr<?= $pagination->total === 1 ? 'y' : 'ies' ?></span>
</form>

<?= $this->insert('partials/audit_rows', ['rows' => $rows]) ?>
<?= $this->insert('partials/admin_pager', ['pagination' => $pagination, 'path' => '/admin/audit', 'query' => $filters]) ?>
