<?php
/** @var string $subscribeTimer */
/** @var \App\Domain\SiteSettings $site */
$errors ??= [];
$values ??= [];
$sv = static fn (string $key): mixed => $values[$key] ?? '';
?>
<form class="subscribe" method="post" action="/subscribe" novalidate>
  <h2 class="subscribe-title">Get new jobs by email</h2>
  <?= $this->insert('partials/csrf') ?>
  <input type="hidden" name="_ts" value="<?= e($subscribeTimer) ?>">
  <div class="hp" aria-hidden="true">
    <label>Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
  </div>
  <div class="subscribe-fields">
    <label class="sr-only" for="sub-email">Email address</label>
    <input id="sub-email" type="email" name="email" value="<?= e($sv('email')) ?>" placeholder="you@example.com" required maxlength="254" autocomplete="email"<?= isset($errors['email']) ? ' aria-invalid="true" aria-describedby="email-error"' : '' ?>>
    <label class="sr-only" for="sub-name">Name (optional)</label>
    <input id="sub-name" type="text" name="name" value="<?= e($sv('name')) ?>" placeholder="Name (optional)" maxlength="100" autocomplete="name">
    <button class="btn-primary" type="submit">Subscribe</button>
  </div>
  <?= $this->insert('partials/field_error', ['field' => 'email']) ?>
  <label class="check consent">
    <input type="checkbox" name="consent" value="1" required<?= $sv('consent') ? ' checked' : '' ?>>
    I agree to receive job alerts from <?= e($site->siteName) ?>. I can unsubscribe at any time.
  </label>
  <?= $this->insert('partials/field_error', ['field' => 'consent']) ?>
</form>
