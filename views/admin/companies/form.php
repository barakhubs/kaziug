<?php
/** @var \App\Domain\Company|null $company */
/** @var array<string, mixed> $values */
/** @var array<string, string> $errors */
use App\Services\ImageInspector;

$this->layout('layouts/admin');
$this->set('title', $company === null ? 'New company' : 'Edit company');
$v = static fn (string $key): mixed => $values[$key] ?? '';
$err = static fn (string $key): string => isset($errors[$key]) ? 'has-error' : '';
$logoRequired = $company === null || !$company->hasLogo;
?>
<header class="page-head">
  <h1><?= $company === null ? 'New company' : 'Edit company' ?></h1>
  <div class="head-actions"><a class="btn-ghost" href="/admin/companies">← All companies</a></div>
</header>

<form class="form-card" method="post" action="<?= e($company === null ? '/admin/companies' : "/admin/companies/{$company->id}") ?>" enctype="multipart/form-data" novalidate>
  <?= $this->insert('partials/csrf') ?>
  <label class="<?= $err('name') ?>">Company or organisation name
    <input name="name" value="<?= e($v('name')) ?>" required maxlength="200">
    <?= $this->insert('partials/field_error', ['field' => 'name']) ?>
  </label>
  <label class="<?= $err('website') ?>">Website <span class="hint">(optional)</span>
    <input type="url" name="website" value="<?= e($v('website')) ?>" maxlength="2048" placeholder="https://company.example">
    <?= $this->insert('partials/field_error', ['field' => 'website']) ?>
  </label>
  <div class="logo-field <?= $err('logo') ?>">
    <label for="logo">Logo <span class="hint">(<?= $logoRequired ? 'required' : 'upload a file to replace it' ?>: PNG, JPEG or WebP, up to <?= e(ImageInspector::MAX_BYTES / 1024) ?> KB and <?= e(ImageInspector::MAX_DIMENSION) ?>×<?= e(ImageInspector::MAX_DIMENSION) ?> px)</span></label>
    <?php if ($company?->hasLogo): ?>
      <div class="logo-preview"><img src="<?= e($company->logoUrl()) ?>" alt="Current logo of <?= e($company->name) ?>"></div>
    <?php endif; ?>
    <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp"<?= $logoRequired ? ' required' : '' ?>>
    <?= $this->insert('partials/field_error', ['field' => 'logo']) ?>
  </div>
  <div class="form-actions">
    <button class="btn-primary" type="submit"><?= $company === null ? 'Create company' : 'Save changes' ?></button>
    <a class="btn-ghost" href="/admin/companies">Cancel</a>
  </div>
</form>
