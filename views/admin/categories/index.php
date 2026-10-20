<?php
/** @var list<\App\Domain\Category> $categories */
$this->layout('layouts/admin');
$this->set('title', 'Categories');
$last = count($categories) - 1;
?>
<header class="page-head">
  <h1>Categories</h1>
  <div class="head-actions"><a class="btn-primary" href="/admin/categories/new">+ New category</a></div>
</header>
<p class="hint">Categories with jobs can't be deleted (deleted jobs still count). Deactivate a category to hide it from the public site instead.</p>

<div class="table-wrap">
  <table>
    <thead><tr><th>Order</th><th>Name</th><th>Slug</th><th>Jobs</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php if ($categories === []): ?>
      <tr><td colspan="6" class="muted center">No categories yet.</td></tr>
    <?php endif; ?>
    <?php foreach ($categories as $i => $category): ?>
      <tr>
        <td class="row-actions order-cell">
          <form class="inline" method="post" action="/admin/categories/<?= e($category->id) ?>/move">
            <?= $this->insert('partials/csrf') ?>
            <button type="submit" name="direction" value="up"<?= $i === 0 ? ' disabled' : '' ?> aria-label="Move <?= e($category->name) ?> up">↑</button>
            <button type="submit" name="direction" value="down"<?= $i === $last ? ' disabled' : '' ?> aria-label="Move <?= e($category->name) ?> down">↓</button>
          </form>
        </td>
        <td><strong><?= e($category->name) ?></strong><?php if ($category->description !== ''): ?><div class="muted small"><?= e($category->description) ?></div><?php endif; ?></td>
        <td class="small"><?= e($category->slug) ?></td>
        <td><?= e($category->jobCount) ?></td>
        <td><span class="pill <?= $category->isActive ? 'active' : 'inactive' ?>"><?= $category->isActive ? 'Active' : 'Inactive' ?></span></td>
        <td class="row-actions">
          <a class="row-link" href="/admin/categories/<?= e($category->id) ?>/edit">Edit</a>
          <form class="inline" method="post" action="/admin/categories/<?= e($category->id) ?>/toggle">
            <?= $this->insert('partials/csrf') ?>
            <input type="hidden" name="active" value="<?= $category->isActive ? '0' : '1' ?>">
            <button type="submit"><?= $category->isActive ? 'Deactivate' : 'Activate' ?></button>
          </form>
          <form class="inline" method="post" action="/admin/categories/<?= e($category->id) ?>/delete" data-confirm="Delete the category “<?= e($category->name) ?>”?">
            <?= $this->insert('partials/csrf') ?>
            <button type="submit" class="danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
