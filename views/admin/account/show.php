<?php
/** @var array<string, string> $errors */
/** @var \App\Domain\AdminUser $admin */
$this->layout('layouts/admin');
$this->set('title', 'Account');
?>
<header class="page-head"><h1>Account</h1></header>

<form class="form-card" method="post" action="/admin/account/password" novalidate>
  <h2>Change password</h2>
  <p class="hint">Signed in as <?= e($admin->email) ?>. Changing the password signs out every other session.</p>
  <?= $this->insert('partials/csrf') ?>
  <label class="<?= isset($errors['current_password']) ? 'has-error' : '' ?>">Current password
    <input type="password" name="current_password" required autocomplete="current-password">
    <?= $this->insert('partials/field_error', ['field' => 'current_password']) ?>
  </label>
  <label class="<?= isset($errors['password']) ? 'has-error' : '' ?>">New password
    <span class="hint">At least <?= \App\Domain\PasswordPolicy::MIN_LENGTH ?> characters. A passphrase of several words works well.</span>
    <input type="password" name="password" required minlength="<?= \App\Domain\PasswordPolicy::MIN_LENGTH ?>" autocomplete="new-password">
    <?= $this->insert('partials/field_error', ['field' => 'password']) ?>
  </label>
  <label class="<?= isset($errors['password_confirmation']) ? 'has-error' : '' ?>">Confirm new password
    <input type="password" name="password_confirmation" required autocomplete="new-password">
    <?= $this->insert('partials/field_error', ['field' => 'password_confirmation']) ?>
  </label>
  <div class="form-actions"><button class="btn-primary" type="submit">Change password</button></div>
</form>

<form class="form-card section-gap" method="post" action="/admin/account/logout-all" data-confirm="Sign out of every session, including this one?">
  <h2>Sessions</h2>
  <p class="hint">Signs out every browser where the admin is signed in, including this one.</p>
  <?= $this->insert('partials/csrf') ?>
  <div class="form-actions"><button class="btn-danger" type="submit">Sign out of all sessions</button></div>
</form>
