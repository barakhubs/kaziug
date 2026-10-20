<?php
/** @var \App\Domain\Newsletter|null $newsletter */
/** @var array<string, mixed> $values */
/** @var array<string, string> $errors */
$this->layout('layouts/admin');
$this->set('title', $newsletter === null ? 'New newsletter' : 'Edit newsletter');
$v = static fn (string $key): mixed => $values[$key] ?? '';
$err = static fn (string $key): string => isset($errors[$key]) ? 'has-error' : '';
?>
<header class="page-head">
  <h1><?= $newsletter === null ? 'New newsletter' : 'Edit newsletter' ?></h1>
  <div class="head-actions">
    <a class="btn-ghost" href="<?= e($newsletter === null ? '/admin/newsletters' : "/admin/newsletters/{$newsletter->id}") ?>">← Back</a>
  </div>
</header>

<form class="form-card newsletter-form" method="post" action="<?= e($newsletter === null ? '/admin/newsletters' : "/admin/newsletters/{$newsletter->id}") ?>" novalidate>
  <?= $this->insert('partials/csrf') ?>
  <label class="<?= $err('subject') ?>">Subject
    <input name="subject" value="<?= e($v('subject')) ?>" required maxlength="200">
    <?= $this->insert('partials/field_error', ['field' => 'subject']) ?>
  </label>
  <label class="<?= $err('preheader') ?>">Preheader <span class="hint">(the preview line many inboxes show after the subject)</span>
    <input name="preheader" value="<?= e($v('preheader')) ?>" maxlength="200">
    <?= $this->insert('partials/field_error', ['field' => 'preheader']) ?>
  </label>

  <div class="<?= $err('body_html') ?>">
    <label for="body_html">Body</label>
    <div class="editor-toolbar js-only" role="toolbar" aria-label="Formatting" data-editor="body_html" hidden>
      <button type="button" data-wrap="strong" title="Bold"><b>B</b></button>
      <button type="button" data-wrap="em" title="Italic"><i>I</i></button>
      <button type="button" data-wrap="h2" title="Heading">H2</button>
      <button type="button" data-wrap="h3" title="Subheading">H3</button>
      <button type="button" data-wrap="p" title="Paragraph">¶</button>
      <button type="button" data-list="ul" title="Bulleted list">• List</button>
      <button type="button" data-list="ol" title="Numbered list">1. List</button>
      <button type="button" data-link title="Link">Link</button>
      <button type="button" data-insert="&lt;hr&gt;" title="Divider">―</button>
    </div>
    <textarea id="body_html" name="body_html" rows="16" class="code-area" required><?= e($v('body_html')) ?></textarea>
    <span class="hint">Simple HTML. Allowed: paragraphs, headings (h2, h3), bold, italic, underline, lists, links (https or mailto), quotes, dividers and line breaks. Anything else is removed when you save.</span>
    <?= $this->insert('partials/field_error', ['field' => 'body_html']) ?>
  </div>

  <label class="<?= $err('body_text') ?>">Plain-text version <span class="hint">(optional; leave empty to generate it from the body)</span>
    <textarea name="body_text" rows="6"><?= e($v('body_text')) ?></textarea>
    <?= $this->insert('partials/field_error', ['field' => 'body_text']) ?>
  </label>
  <p class="hint">Every email automatically ends with your physical address and an unsubscribe link.</p>
  <div class="form-actions">
    <button class="btn-primary" type="submit"><?= $newsletter === null ? 'Save draft' : 'Save changes' ?></button>
  </div>
</form>
