<?php
/** @var \App\Domain\Popup|null $popup */
/** @var array<string, mixed> $values */
/** @var array<string, string> $errors */
use App\Domain\PopupTrigger;
use App\Services\ImageInspector;
use App\Validation\PopupValidator;

$this->layout('layouts/admin');
$this->set('title', $popup === null ? 'New popup' : 'Edit popup');
$this->start('head');
?>
  <link rel="stylesheet" href="/assets/vendor/quill/quill.snow.css">
  <script src="/assets/vendor/quill/quill.js" defer></script>
<?php
$this->end();
$v = static fn (string $key): mixed => $values[$key] ?? '';
$err = static fn (string $key): string => isset($errors[$key]) ? ' has-error' : '';
$imageUrl = $popup?->adminImageUrl();
?>
<header class="page-head">
  <h1><?= $popup === null ? 'New popup' : 'Edit popup' ?><?php if ($popup !== null): $status = $popup->statusLabel(); ?> <span class="pill <?= e(strtolower($status)) ?>"><?= e($status) ?></span><?php endif; ?></h1>
  <div class="head-actions"><a class="btn-ghost" href="/admin/popups">← All popups</a></div>
</header>

<?php if ($errors !== []): ?>
  <div class="flash error" role="alert">Please fix the highlighted fields.</div>
<?php endif; ?>

<form class="blog-form" method="post" action="<?= e($popup === null ? '/admin/popups' : "/admin/popups/{$popup->id}") ?>" enctype="multipart/form-data" novalidate>
  <?= $this->insert('partials/csrf') ?>
  <div class="blog-grid">
    <section class="form-card blog-main">
      <label class="<?= $err('name') ?>">Internal name <span class="hint">(only you see this, e.g. “CV help offer, November”)</span>
        <input name="name" value="<?= e($v('name')) ?>" required maxlength="120">
        <?= $this->insert('partials/field_error', ['field' => 'name']) ?>
      </label>
      <label class="<?= $err('heading') ?>">Heading <span class="hint">(shown at the top of the popup)</span>
        <input name="heading" value="<?= e($v('heading')) ?>" required maxlength="150">
        <?= $this->insert('partials/field_error', ['field' => 'heading']) ?>
      </label>
      <div class="editor-field<?= $err('body_html') ?>">
        <label for="body_html">Message <span class="hint">(keep it short: a sentence or two reads best)</span></label>
        <div class="rich-editor short js-only" data-rich-editor="body_html" hidden></div>
        <textarea id="body_html" name="body_html" rows="6" class="code-area" required><?= e($v('body_html')) ?></textarea>
        <?= $this->insert('partials/field_error', ['field' => 'body_html']) ?>
      </div>
      <div class="grid2">
        <label class="<?= $err('button_label') ?>">Button text <span class="hint">(optional)</span>
          <input name="button_label" value="<?= e($v('button_label')) ?>" maxlength="60" placeholder="Get CV help">
          <?= $this->insert('partials/field_error', ['field' => 'button_label']) ?>
        </label>
        <label class="<?= $err('button_url') ?>">Button link
          <input name="button_url" value="<?= e($v('button_url')) ?>" maxlength="2048" placeholder="/cv-help or https://…">
          <?= $this->insert('partials/field_error', ['field' => 'button_url']) ?>
        </label>
      </div>
    </section>

    <aside class="blog-side">
      <section class="form-card">
        <h2>Showing</h2>
        <label class="check"><input type="checkbox" name="activate" value="1"<?= $v('activate') ? ' checked' : '' ?>> Active <span class="hint">(switches off any other popup)</span></label>
        <?php if ($popup !== null): ?>
          <label class="check"><input type="checkbox" name="show_again" value="1"> Show again to visitors who already saw it</label>
        <?php endif; ?>
        <label class="<?= $err('trigger_type') ?>">Opens
          <select name="trigger_type">
            <?php foreach (PopupTrigger::cases() as $trigger): ?>
              <option value="<?= e($trigger->value) ?>"<?= $v('trigger_type') === $trigger->value ? ' selected' : '' ?>><?= e($trigger->label()) ?></option>
            <?php endforeach; ?>
          </select>
          <?= $this->insert('partials/field_error', ['field' => 'trigger_type']) ?>
        </label>
        <label class="<?= $err('delay_seconds') ?>">Delay (seconds) <span class="hint">(for “after a delay”, and on phones for “when leaving”)</span>
          <input type="number" name="delay_seconds" value="<?= e($v('delay_seconds')) ?>" min="0" max="<?= e(PopupValidator::MAX_DELAY) ?>" class="short">
          <?= $this->insert('partials/field_error', ['field' => 'delay_seconds']) ?>
        </label>
        <label class="<?= $err('starts_at') ?>">Starts <span class="hint">(optional)</span>
          <input type="datetime-local" name="starts_at" value="<?= e($v('starts_at')) ?>">
          <?= $this->insert('partials/field_error', ['field' => 'starts_at']) ?>
        </label>
        <label class="<?= $err('ends_at') ?>">Ends <span class="hint">(optional)</span>
          <input type="datetime-local" name="ends_at" value="<?= e($v('ends_at')) ?>">
          <?= $this->insert('partials/field_error', ['field' => 'ends_at']) ?>
        </label>
        <div class="form-actions stacked">
          <button class="btn-primary" type="submit"><?= $popup === null ? 'Create popup' : 'Save changes' ?></button>
        </div>
      </section>

      <section class="form-card">
        <h2>Image <span class="hint">(optional)</span></h2>
        <div class="<?= $err('image') ?>">
          <?php if ($imageUrl !== null): ?>
            <img class="cover-preview" src="<?= e($imageUrl) ?>" alt="Current image">
            <label class="check"><input type="checkbox" name="remove_image" value="1"> Remove the image</label>
          <?php endif; ?>
          <label for="image">Upload <span class="hint">(PNG, JPEG or WebP up to <?= e(ImageInspector::MAX_COVER_BYTES / 1024) ?> KB; shown above the heading)</span></label>
          <input type="file" id="image" name="image" accept="image/png,image/jpeg,image/webp">
          <?= $this->insert('partials/field_error', ['field' => 'image']) ?>
        </div>
        <label class="<?= $err('image_alt') ?>">Alt text <span class="hint">(describe the image for screen readers)</span>
          <input name="image_alt" value="<?= e($v('image_alt')) ?>" maxlength="200">
          <?= $this->insert('partials/field_error', ['field' => 'image_alt']) ?>
        </label>
      </section>
    </aside>
  </div>
</form>
