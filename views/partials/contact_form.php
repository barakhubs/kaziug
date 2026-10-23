<?php
/** @var array<string, string> $errors */
/** @var array<string, mixed> $values */
/** @var string $formAction */
/** @var string $subscribeTimer */
/** @var string $messageLabel */
/** @var string $messagePlaceholder */
/** @var string $submitLabel */
$cv = static fn (string $key): mixed => $values[$key] ?? '';
$invalid = static fn (string $key): string => isset($errors[$key]) ? ' aria-invalid="true" aria-describedby="' . $key . '-error"' : '';
?>
<form class="contact-form" method="post" action="<?= e($formAction) ?>" novalidate>
  <?= $this->insert('partials/csrf') ?>
  <input type="hidden" name="_ts" value="<?= e($subscribeTimer) ?>">
  <div class="hp" aria-hidden="true"><label>Leave this empty <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

  <?php if (isset($errors['form'])): ?>
    <p class="form-alert" role="alert"><?= e($errors['form']) ?></p>
  <?php endif; ?>

  <div class="form-row">
    <label>Your name
      <input name="name" value="<?= e($cv('name')) ?>" required maxlength="100" autocomplete="name"<?= $invalid('name') ?>>
      <?= $this->insert('partials/field_error', ['field' => 'name']) ?>
    </label>
    <label>Email
      <input type="email" name="email" value="<?= e($cv('email')) ?>" required maxlength="254" autocomplete="email"<?= $invalid('email') ?>>
      <?= $this->insert('partials/field_error', ['field' => 'email']) ?>
    </label>
  </div>
  <label><span>Phone / WhatsApp <span class="muted">(optional)</span></span>
    <input type="tel" name="phone" value="<?= e($cv('phone')) ?>" maxlength="30" autocomplete="tel" placeholder="+256 7…"<?= $invalid('phone') ?>>
    <?= $this->insert('partials/field_error', ['field' => 'phone']) ?>
  </label>
  <label><?= e($messageLabel) ?>
    <textarea name="message" rows="6" required maxlength="5000" placeholder="<?= e($messagePlaceholder) ?>"<?= $invalid('message') ?>><?= e($cv('message')) ?></textarea>
    <?= $this->insert('partials/field_error', ['field' => 'message']) ?>
  </label>
  <button class="btn-primary" type="submit"><?= e($submitLabel) ?></button>
  <p class="form-note muted">We use your details only to reply to you. See our <a href="/privacy">Privacy Policy</a>.</p>
</form>
