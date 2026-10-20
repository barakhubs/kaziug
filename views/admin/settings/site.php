<?php
/** @var array<string, mixed> $values */
/** @var array<string, string> $errors */
/** @var \App\Domain\SiteSettings $site */
$this->layout('layouts/admin');
$this->set('title', 'Site settings');
$v = static fn (string $key): mixed => $values[$key] ?? '';
$err = static fn (string $key): string => isset($errors[$key]) ? 'has-error' : '';
?>
<header class="page-head"><h1>Site settings</h1></header>

<?php if ($site->maintenanceMode): ?>
  <div class="flash info">Maintenance mode is <strong>on</strong>: visitors see a maintenance page. You can still browse the site while signed in.</div>
<?php endif; ?>

<form class="form-card" method="post" action="/admin/settings" enctype="multipart/form-data" novalidate>
  <?= $this->insert('partials/csrf') ?>
  <div class="grid2">
    <label class="<?= $err('site_name') ?>">Site name
      <input name="site_name" value="<?= e($v('site_name')) ?>" required maxlength="80">
      <?= $this->insert('partials/field_error', ['field' => 'site_name']) ?>
    </label>
    <label class="<?= $err('tagline') ?>">Tagline
      <input name="tagline" value="<?= e($v('tagline')) ?>" maxlength="120">
      <?= $this->insert('partials/field_error', ['field' => 'tagline']) ?>
    </label>
    <label class="<?= $err('contact_email') ?>">Contact email
      <input type="email" name="contact_email" value="<?= e($v('contact_email')) ?>">
      <?= $this->insert('partials/field_error', ['field' => 'contact_email']) ?>
    </label>
    <label class="<?= $err('jobs_per_page') ?>">Jobs per page
      <input type="number" name="jobs_per_page" min="1" max="100" value="<?= e($v('jobs_per_page')) ?>" required class="short">
      <?= $this->insert('partials/field_error', ['field' => 'jobs_per_page']) ?>
    </label>
    <label class="full <?= $err('physical_address') ?>">Physical address <span class="hint">(shown in the footer and required in every newsletter)</span>
      <textarea name="physical_address" rows="3" maxlength="500"><?= e($v('physical_address')) ?></textarea>
      <?= $this->insert('partials/field_error', ['field' => 'physical_address']) ?>
    </label>
    <label class="full <?= $err('footer_text') ?>">Footer text
      <textarea name="footer_text" rows="2" maxlength="500"><?= e($v('footer_text')) ?></textarea>
      <?= $this->insert('partials/field_error', ['field' => 'footer_text']) ?>
    </label>
    <label class="full <?= $err('meta_description') ?>">Default meta description <span class="hint">(for search engines; up to 300 characters)</span>
      <textarea name="meta_description" rows="2" maxlength="300"><?= e($v('meta_description')) ?></textarea>
      <?= $this->insert('partials/field_error', ['field' => 'meta_description']) ?>
    </label>
    <div class="full logo-field <?= $err('logo') ?>">
      <label for="logo">Logo <span class="hint">(PNG, JPEG or WebP, up to 512 KB and 2000×2000 px; shown in the header instead of the site name)</span></label>
      <?php if ($site->hasLogo()): ?>
        <div class="logo-preview"><img src="/media/logo?v=<?= e($site->logoVersion) ?>" alt="Current logo"></div>
        <label class="check"><input type="checkbox" name="remove_logo" value="1"> Remove the current logo</label>
      <?php endif; ?>
      <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp">
      <?= $this->insert('partials/field_error', ['field' => 'logo']) ?>
    </div>
    <label class="check full"><input type="checkbox" name="maintenance_mode" value="1"<?= $v('maintenance_mode') ? ' checked' : '' ?>> Maintenance mode (visitors see a “back soon” page; the back office keeps working)</label>
  </div>
  <div class="form-actions"><button class="btn-primary" type="submit">Save settings</button></div>
</form>
