<?php
/** @var \App\Domain\SmtpSettings $smtp */
/** @var array<string, mixed> $values */
/** @var array<string, string> $errors */
/** @var string $adminEmail */
use App\Core\Format;
use App\Domain\SmtpSettings;

$this->layout('layouts/admin');
$this->set('title', 'SMTP settings');
$v = static fn (string $key): mixed => $values[$key] ?? '';
$err = static fn (string $key): string => isset($errors[$key]) ? 'has-error' : '';
?>
<?= $this->insert('partials/settings_tabs') ?>

<div class="panel mail-status">
  <?php if ($smtp->isConfigured()): ?>
    <p><span class="pill active">Mail is configured</span>
      Sending as <strong><?= e($smtp->fromName !== '' ? $smtp->fromName . ' <' . $smtp->fromEmail . '>' : $smtp->fromEmail) ?></strong>
      through <?= e($smtp->host) ?>:<?= e($smtp->port) ?>.</p>
  <?php else: ?>
    <p><span class="pill failed">Mail is not configured</span> Subscription confirmations and newsletters can’t be sent until you save these settings.</p>
  <?php endif; ?>
  <?php if ($smtp->lastTestedAt !== null): ?>
    <p class="muted">Last test <?= e(Format::dateTime($smtp->lastTestedAt)) ?>:
      <span class="<?= $smtp->lastTestSucceeded() ? 'ok-text' : 'field-error' ?>"><?= e($smtp->lastTestResult) ?></span></p>
  <?php endif; ?>
</div>

<form class="form-card section-gap" method="post" action="/admin/settings/smtp" novalidate autocomplete="off">
  <h2>Server</h2>
  <?= $this->insert('partials/csrf') ?>
  <div class="grid2">
    <label class="<?= $err('host') ?>">Host
      <input name="host" value="<?= e($v('host')) ?>" required placeholder="smtp.example.com">
      <?= $this->insert('partials/field_error', ['field' => 'host']) ?>
    </label>
    <label class="<?= $err('port') ?>">Port
      <input name="port" inputmode="numeric" value="<?= e($v('port')) ?>" required class="short">
      <?= $this->insert('partials/field_error', ['field' => 'port']) ?>
    </label>
    <label class="<?= $err('encryption') ?>">Encryption
      <select name="encryption">
        <?php foreach (SmtpSettings::ENCRYPTIONS as $value => $label): ?>
          <option value="<?= e($value) ?>"<?= $v('encryption') === $value ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="<?= $err('username') ?>">Username
      <input name="username" value="<?= e($v('username')) ?>" autocomplete="off">
      <?= $this->insert('partials/field_error', ['field' => 'username']) ?>
    </label>
    <label class="<?= $err('password') ?>">Password
      <input type="password" name="password" value="" autocomplete="new-password"
        placeholder="<?= $smtp->hasPassword ? '•••••••• (saved, leave blank to keep)' : 'Not set' ?>">
      <?= $this->insert('partials/field_error', ['field' => 'password']) ?>
    </label>
    <?php if ($smtp->hasPassword): ?>
      <label class="check"><input type="checkbox" name="clear_password" value="1"> Remove the saved password</label>
    <?php endif; ?>
  </div>
  <h2>Sender</h2>
  <div class="grid2">
    <label class="<?= $err('from_name') ?>">From name
      <input name="from_name" value="<?= e($v('from_name')) ?>" placeholder="<?= e($site->siteName) ?>">
      <?= $this->insert('partials/field_error', ['field' => 'from_name']) ?>
    </label>
    <label class="<?= $err('from_email') ?>">From email
      <input type="email" name="from_email" value="<?= e($v('from_email')) ?>" required placeholder="jobs@example.com">
      <?= $this->insert('partials/field_error', ['field' => 'from_email']) ?>
    </label>
    <label class="<?= $err('reply_to') ?>">Reply-to <span class="hint">(optional)</span>
      <input type="email" name="reply_to" value="<?= e($v('reply_to')) ?>">
      <?= $this->insert('partials/field_error', ['field' => 'reply_to']) ?>
    </label>
  </div>
  <p class="hint">The password is encrypted before it is stored and is never shown again.</p>
  <div class="form-actions"><button class="btn-primary" type="submit">Save SMTP settings</button></div>
</form>

<form class="form-card section-gap" method="post" action="/admin/settings/smtp/test" novalidate>
  <h2>Send a test email</h2>
  <?= $this->insert('partials/csrf') ?>
  <label>Send to
    <input type="email" name="test_to" value="<?= e($adminEmail) ?>" required>
  </label>
  <div class="form-actions"><button class="btn-ghost" type="submit"<?= $smtp->isConfigured() ? '' : ' disabled' ?>>Send test email</button></div>
</form>
