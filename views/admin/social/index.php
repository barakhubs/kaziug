<?php
/** @var list<\App\Domain\SocialProfile> $profiles */
$this->layout('layouts/admin');
$this->set('title', 'Social profiles');
$last = count($profiles) - 1;
?>
<?= $this->insert('partials/settings_tabs') ?>
<div class="section-head">
  <p class="hint">Visible profiles appear as icons in the public site footer, in this order.</p>
  <a class="btn-primary" href="/admin/settings/social/new">+ New profile</a>
</div>

<div class="table-wrap">
  <table>
    <thead><tr><th>Order</th><th>Icon</th><th>Platform</th><th>Link</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php if ($profiles === []): ?>
      <tr><td colspan="6" class="muted center">No social profiles yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($profiles as $i => $profile): ?>
      <tr>
        <td class="row-actions order-cell">
          <form class="inline" method="post" action="/admin/settings/social/<?= e($profile->id) ?>/move">
            <?= $this->insert('partials/csrf') ?>
            <button type="submit" name="direction" value="up"<?= $i === 0 ? ' disabled' : '' ?> aria-label="Move <?= e($profile->label) ?> up">↑</button>
            <button type="submit" name="direction" value="down"<?= $i === $last ? ' disabled' : '' ?> aria-label="Move <?= e($profile->label) ?> down">↓</button>
          </form>
        </td>
        <td class="icon-cell"><?= $this->insert('partials/icon', ['icon' => $profile->icon]) ?></td>
        <td><strong><?= e($profile->platform) ?></strong><div class="muted small"><?= e($profile->label) ?></div></td>
        <td class="small"><a href="<?= e($profile->url) ?>" target="_blank" rel="noopener noreferrer"><?= e($profile->url) ?></a></td>
        <td><span class="pill <?= $profile->isVisible ? 'visible' : 'hidden-pill' ?>"><?= $profile->isVisible ? 'Visible' : 'Hidden' ?></span></td>
        <td class="row-actions">
          <a class="row-link" href="/admin/settings/social/<?= e($profile->id) ?>/edit">Edit</a>
          <form class="inline" method="post" action="/admin/settings/social/<?= e($profile->id) ?>/visibility">
            <?= $this->insert('partials/csrf') ?>
            <input type="hidden" name="visible" value="<?= $profile->isVisible ? '0' : '1' ?>">
            <button type="submit"><?= $profile->isVisible ? 'Hide' : 'Show' ?></button>
          </form>
          <form class="inline" method="post" action="/admin/settings/social/<?= e($profile->id) ?>/delete" data-confirm="Delete the <?= e($profile->platform) ?> profile?">
            <?= $this->insert('partials/csrf') ?>
            <button type="submit" class="danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
