<?php
use App\Services\ImageInspector;
?>
<dialog id="companyDialog" aria-labelledby="companyDialogTitle">
  <form class="job-form" method="post" action="/admin/companies" enctype="multipart/form-data" novalidate data-company-form>
    <?= $this->insert('partials/csrf') ?>
    <div class="dialog-head">
      <h2 id="companyDialogTitle">Add a company</h2>
      <button type="button" class="link-btn" data-close-dialog aria-label="Close">✕</button>
    </div>
    <div class="flash error" role="alert" data-form-error hidden></div>
    <label data-field="name">Company or organisation name
      <input name="name" required maxlength="200">
      <span class="field-error" hidden></span>
    </label>
    <label data-field="website">Website <span class="hint">(optional)</span>
      <input type="url" name="website" maxlength="2048" placeholder="https://company.example">
      <span class="field-error" hidden></span>
    </label>
    <label data-field="logo">Logo <span class="hint">(required: PNG, JPEG or WebP, up to <?= e(ImageInspector::MAX_BYTES / 1024) ?> KB)</span>
      <input type="file" name="logo" accept="image/png,image/jpeg,image/webp" required>
      <span class="field-error" hidden></span>
    </label>
    <div class="dialog-foot">
      <button type="button" class="btn-ghost" data-close-dialog>Cancel</button>
      <button type="submit" class="btn-primary">Save company</button>
    </div>
  </form>
</dialog>
