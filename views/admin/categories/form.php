<?php
/** @var \App\Domain\Category|null $category */
/** @var array<string, mixed> $values */
/** @var array<string, string> $errors */
$this->layout('layouts/admin');
$this->set('title', $category === null ? 'New category' : 'Edit category');
$v = static fn (string $key): mixed => $values[$key] ?? '';
$err = static fn (string $key): string => isset($errors[$key]) ? 'has-error' : '';
?>
<header class="page-head">
  <h1><?= $category === null ? 'New category' : 'Edit category' ?></h1>
  <div class="head-actions"><a class="btn-ghost" href="/admin/categories">← All categories</a></div>
</header>

<form class="form-card" method="post" action="<?= e($category === null ? '/admin/categories' : "/admin/categories/{$category->id}") ?>" novalidate>
  <?= $this->insert('partials/csrf') ?>
  <label class="<?= $err('name') ?>">Name
    <input name="name" value="<?= e($v('name')) ?>" required maxlength="100">
    <?= $this->insert('partials/field_error', ['field' => 'name']) ?>
  </label>
  <label class="<?= $err('slug') ?>">Slug <span class="hint">(used in /categories/&lt;slug&gt;; leave empty to generate it from the name)</span>
    <input name="slug" value="<?= e($v('slug')) ?>" maxlength="120" pattern="[a-z0-9]+(-[a-z0-9]+)*">
    <?= $this->insert('partials/field_error', ['field' => 'slug']) ?>
  </label>
  <label class="<?= $err('description') ?>">Description <span class="hint">(optional, shown on the category page)</span>
    <textarea name="description" rows="3" maxlength="1000"><?= e($v('description')) ?></textarea>
    <?= $this->insert('partials/field_error', ['field' => 'description']) ?>
  </label>
  <?php if ($category === null): ?>
    <label class="check"><input type="checkbox" name="is_active" value="1"<?= $v('is_active') ? ' checked' : '' ?>> Active (visible on the public site)</label>
  <?php endif; ?>
  <div class="form-actions">
    <button class="btn-primary" type="submit"><?= $category === null ? 'Create category' : 'Save changes' ?></button>
    <a class="btn-ghost" href="/admin/categories">Cancel</a>
  </div>
</form>
