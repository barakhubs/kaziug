<?php
/** @var list<array<string, mixed>> $rows */
use App\Core\Format;

$describe = static function (array $metadata): string {
    $parts = [];
    foreach ($metadata as $key => $value) {
        $text = is_scalar($value) || $value === null ? var_export($value, true) : json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $parts[] = $key . ': ' . trim((string) $text, "'");
    }

    return mb_strimwidth(implode(' · ', $parts), 0, 160, '…');
};
?>
<div class="table-wrap">
  <table>
    <thead><tr><th>When</th><th>Who</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead>
    <tbody>
    <?php if ($rows === []): ?>
      <tr><td colspan="6" class="muted center">No entries.</td></tr>
    <?php endif; ?>
    <?php foreach ($rows as $row): ?>
      <tr>
        <td class="small nowrap"><?= e(Format::dbDateTime($row['created_at'])) ?></td>
        <td class="small"><?= e($row['actor']) ?></td>
        <td><code><?= e($row['action']) ?></code></td>
        <td class="small"><?= e($row['entity']) ?><?= $row['entity_id'] !== null ? ' #' . e($row['entity_id']) : '' ?></td>
        <td class="small"><?= e($describe($row['metadata'])) ?></td>
        <td class="small"><?= e($row['ip'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
