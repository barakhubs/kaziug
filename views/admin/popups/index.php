<?php
/** @var list<\App\Domain\Popup> $popups */
use App\Core\Format;

$this->layout('layouts/admin');
$this->set('title', 'Popups');
?>
<header class="page-head">
  <h1>Popups</h1>
  <div class="head-actions"><a class="btn-primary" href="/admin/popups/new">+ New popup</a></div>
</header>
<p class="hint">The active popup appears on the home page and job pages, once per visitor (their browser remembers it; nothing is stored about them). Only one popup can be active: activating one switches off the other.</p>

<div class="table-wrap">
  <table>
    <thead><tr><th>Popup</th><th>Opens</th><th>Dates</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php if ($popups === []): ?>
      <tr><td colspan="5" class="muted center">No popups yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($popups as $popup): $status = $popup->statusLabel(); ?>
      <tr>
        <td>
          <strong><a href="/admin/popups/<?= e($popup->id) ?>/edit"><?= e($popup->name) ?></a></strong>
          <div class="sub"><?= e($popup->heading) ?></div>
        </td>
        <td class="small"><?= e($popup->trigger->shortLabel()) ?><?= $popup->trigger->value === 'scroll' ? '' : ' · ' . e($popup->delaySeconds) . ' s' ?></td>
        <td class="small nowrap">
          <?php if ($popup->startsAt === null && $popup->endsAt === null): ?>Always<?php else: ?>
            <?= e($popup->startsAt !== null ? Format::dateTime($popup->startsAt) : 'Now') ?> → <?= e($popup->endsAt !== null ? Format::dateTime($popup->endsAt) : 'no end') ?>
          <?php endif; ?>
        </td>
        <td><span class="pill <?= e(strtolower($status)) ?>"><?= e($status) ?></span></td>
        <td class="row-actions">
          <a class="row-link" href="/admin/popups/<?= e($popup->id) ?>/edit">Edit</a>
          <form class="inline" method="post" action="/admin/popups/<?= e($popup->id) ?>/<?= $popup->isActive ? 'deactivate' : 'activate' ?>">
            <?= $this->insert('partials/csrf') ?>
            <button type="submit"><?= $popup->isActive ? 'Switch off' : 'Activate' ?></button>
          </form>
          <form class="inline" method="post" action="/admin/popups/<?= e($popup->id) ?>/delete" data-confirm="Delete the popup “<?= e($popup->name) ?>”?">
            <?= $this->insert('partials/csrf') ?>
            <button type="submit" class="danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
