<?php
/** @var string $email */
/** @var string|null $error */
$this->layout('layouts/admin_auth');
?>
<form class="auth-card" method="post" action="/admin/login" novalidate>
  <h1>Back office sign in</h1>
  <?= $this->insert('partials/flash') ?>
  <?= $this->insert('partials/csrf') ?>
  <label>Email
    <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="username" autofocus>
  </label>
  <label>Password
    <input type="password" name="password" required autocomplete="current-password">
  </label>
  <button class="btn-primary" type="submit">Sign in</button>
  <?php if ($error !== null): ?>
    <p class="error" role="alert"><?= e($error) ?></p>
  <?php endif; ?>
</form>
