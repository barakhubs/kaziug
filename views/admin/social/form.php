<?php
/** @var \App\Domain\SocialProfile|null $profile */
/** @var array<string, mixed> $values */
/** @var array<string, string> $errors */
use App\Domain\SocialIcon;

$this->layout('layouts/admin');
$this->set('title', $profile === null ? 'New social profile' : 'Edit social profile');
$v = static fn (string $key): mixed => $values[$key] ?? '';
$err = static fn (string $key): string => isset($errors[$key]) ? 'has-error' : '';
?>
<header class="page-head">
  <h1><?= $profile === null ? 'New social profile' : 'Edit social profile' ?></h1>
  <div class="head-actions"><a class="btn-ghost" href="/admin/social">← All profiles</a></div>
</header>

<form class="form-card" method="post" action="<?= e($profile === null ? '/admin/social' : "/admin/social/{$profile->id}") ?>" novalidate>
  <?= $this->insert('partials/csrf') ?>
  <label class="<?= $err('platform') ?>">Platform
    <input name="platform" value="<?= e($v('platform')) ?>" required maxlength="60" placeholder="e.g. LinkedIn">
    <?= $this->insert('partials/field_error', ['field' => 'platform']) ?>
  </label>
  <label class="<?= $err('label') ?>">Label <span class="hint">(read by screen readers and shown on hover)</span>
    <input name="label" value="<?= e($v('label')) ?>" required maxlength="100" placeholder="e.g. KaziUG on LinkedIn">
    <?= $this->insert('partials/field_error', ['field' => 'label']) ?>
  </label>
  <label class="<?= $err('url') ?>">Profile URL
    <input type="url" name="url" value="<?= e($v('url')) ?>" required placeholder="https://">
    <?= $this->insert('partials/field_error', ['field' => 'url']) ?>
  </label>
  <fieldset class="icon-picker <?= $err('icon_key') ?>">
    <legend>Icon</legend>
    <?php foreach (SocialIcon::cases() as $icon): ?>
      <label class="icon-option">
        <input type="radio" name="icon_key" value="<?= e($icon->value) ?>"<?= $v('icon_key') === $icon->value ? ' checked' : '' ?>>
        <?= $this->insert('partials/icon', ['icon' => $icon]) ?>
        <span><?= e($icon->label()) ?></span>
      </label>
    <?php endforeach; ?>
    <?= $this->insert('partials/field_error', ['field' => 'icon_key']) ?>
  </fieldset>
  <label class="check"><input type="checkbox" name="is_visible" value="1"<?= $v('is_visible') ? ' checked' : '' ?>> Show in the site footer</label>
  <div class="form-actions">
    <button class="btn-primary" type="submit"><?= $profile === null ? 'Add profile' : 'Save changes' ?></button>
    <a class="btn-ghost" href="/admin/social">Cancel</a>
  </div>
</form>
