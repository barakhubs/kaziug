<?php
/** @var \App\Domain\Paginated<\App\Domain\Newsletter> $result */
/** @var array<int, \App\Domain\DeliveryCounts> $counts */
use App\Core\Format;

$this->layout('layouts/admin');
$this->set('title', 'Newsletters');
?>
<header class="page-head">
  <h1>Newsletters</h1>
  <div class="head-actions"><a class="btn-primary" href="/admin/newsletters/new">+ New newsletter</a></div>
</header>

<div class="table-wrap">
  <table>
    <thead><tr><th>Subject</th><th>Status</th><th>When</th><th>Queued</th><th>Sent</th><th>Failed</th></tr></thead>
    <tbody>
    <?php if ($result->items === []): ?>
      <tr><td colspan="6" class="muted center">No newsletters yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($result->items as $n): $c = $counts[$n->id]; ?>
      <tr>
        <td><strong><a href="/admin/newsletters/<?= e($n->id) ?>"><?= e($n->subject) ?></a></strong>
          <?php if ($n->createdBy !== null): ?><div class="muted small">by <?= e($n->createdBy) ?></div><?php endif; ?></td>
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
<?= $this->insert('partials/admin_pager', ['pagination' => $result->pagination, 'path' => '/admin/newsletters', 'query' => []]) ?>
