<?php
/** @var \App\Domain\BlogPost|null $post */
/** @var array<string, mixed> $values */
/** @var array<string, string> $errors */
/** @var list<\App\Domain\Category> $categories */
use App\Core\Format;
use App\Services\ImageInspector;
use App\Validation\BlogPostValidator;

$this->layout('layouts/admin');
$this->set('title', $post === null ? 'New post' : 'Edit post');
$this->start('head');
?>
  <link rel="stylesheet" href="/assets/vendor/quill/quill.snow.css">
  <script src="/assets/vendor/quill/quill.js" defer></script>
<?php
$this->end();
$v = static fn (string $key): mixed => $values[$key] ?? '';
$err = static fn (string $key): string => isset($errors[$key]) ? ' has-error' : '';
$status = $post?->statusLabel();
$coverUrl = $post?->adminCoverUrl();
?>
<header class="page-head">
  <div>
    <h1><?= $post === null ? 'New post' : 'Edit post' ?><?php if ($status !== null): ?> <span class="pill <?= e(strtolower($status)) ?>"><?= e($status) ?></span><?php endif; ?></h1>
    <?php if ($post?->isScheduled()): ?>
      <p class="muted">Goes live on <?= e(Format::dateTime($post->publishedAt)) ?>.</p>
    <?php elseif ($post?->isLive()): ?>
      <p class="muted">Published <?= e(Format::dateTime($post->publishedAt)) ?> · <?= e($post->readingMinutes()) ?> min read</p>
    <?php endif; ?>
  </div>
  <div class="head-actions">
    <a class="btn-ghost" href="/admin/blog">← All posts</a>
    <?php if ($post?->isLive()): ?>
      <a class="btn-ghost" href="/blog/<?= e($post->slug) ?>" target="_blank" rel="noopener">View on site ↗</a>
    <?php endif; ?>
    <?php if ($post !== null): ?><a class="btn-ghost" href="/admin/blog/new">+ New post</a><?php endif; ?>
  </div>
</header>

<?php if ($errors !== []): ?>
  <div class="flash error" role="alert">Please fix the highlighted fields.</div>
<?php endif; ?>

<form class="blog-form" method="post" action="<?= e($post === null ? '/admin/blog' : "/admin/blog/{$post->id}") ?>" enctype="multipart/form-data" novalidate>
  <?= $this->insert('partials/csrf') ?>
  <div class="blog-grid">
    <section class="form-card blog-main">
      <label class="<?= $err('title') ?>">Title <span class="hint">(the page headline; keep it under about 60 characters for search results)</span>
        <input name="title" value="<?= e($v('title')) ?>" required maxlength="200" data-count-ideal="60">
        <?= $this->insert('partials/field_error', ['field' => 'title']) ?>
      </label>

      <div class="editor-field<?= $err('body_html') ?>">
        <label for="body_html">Article</label>
        <div class="rich-editor js-only" data-rich-editor="body_html" hidden></div>
        <textarea id="body_html" name="body_html" rows="18" class="code-area" required><?= e($v('body_html')) ?></textarea>
        <span class="hint">Use “Heading” for section titles: search engines read them as the outline of the article.</span>
        <?= $this->insert('partials/field_error', ['field' => 'body_html']) ?>
      </div>

      <label class="<?= $err('excerpt') ?>">Excerpt <span class="hint">(optional, up to 300 characters; shown on the blog list)</span>
        <textarea name="excerpt" rows="3" maxlength="300"><?= e($v('excerpt')) ?></textarea>
        <?= $this->insert('partials/field_error', ['field' => 'excerpt']) ?>
      </label>
    </section>

    <aside class="blog-side">
      <section class="form-card">
        <h2>Publishing</h2>
        <label class="<?= $err('publish_at') ?>">Publish date <span class="hint">(empty = now; a future date schedules the post)</span>
          <input type="datetime-local" name="publish_at" value="<?= e($v('publish_at')) ?>">
          <?= $this->insert('partials/field_error', ['field' => 'publish_at']) ?>
        </label>
        <div class="form-actions stacked">
          <?php if ($post === null || !$post->isPublished): ?>
            <button class="btn-primary" type="submit" name="intent" value="publish">Publish</button>
            <button class="btn-ghost" type="submit" name="intent" value="draft">Save draft</button>
          <?php else: ?>
            <button class="btn-primary" type="submit" name="intent" value="publish">Save changes</button>
            <button class="btn-ghost" type="submit" name="intent" value="draft">Unpublish (back to draft)</button>
          <?php endif; ?>
        </div>
      </section>

      <section class="form-card">
        <h2>Cover image</h2>
        <div class="<?= $err('cover') ?>">
          <?php if ($coverUrl !== null): ?>
            <img class="cover-preview" src="<?= e($coverUrl) ?>" alt="Current cover">
          <?php endif; ?>
          <label for="cover">Image <span class="hint">(<?= $coverUrl === null ? 'needed to publish' : 'upload to replace' ?>: PNG, JPEG or WebP up to <?= e(ImageInspector::MAX_COVER_BYTES / 1024) ?> KB; 1200×630 px is ideal for sharing)</span></label>
          <input type="file" id="cover" name="cover" accept="image/png,image/jpeg,image/webp">
          <?= $this->insert('partials/field_error', ['field' => 'cover']) ?>
        </div>
        <label class="<?= $err('cover_alt') ?>">Alt text <span class="hint">(describe the image for screen readers and search engines)</span>
          <input name="cover_alt" value="<?= e($v('cover_alt')) ?>" maxlength="200">
          <?= $this->insert('partials/field_error', ['field' => 'cover_alt']) ?>
        </label>
      </section>

      <section class="form-card">
        <h2>Search engines</h2>
        <label class="<?= $err('meta_description') ?>">Meta description <span class="hint">(the snippet under the title in Google)</span>
          <textarea name="meta_description" rows="3" maxlength="170" data-count-ideal="<?= e(BlogPostValidator::META_DESCRIPTION_IDEAL) ?>"><?= e($v('meta_description')) ?></textarea>
          <?= $this->insert('partials/field_error', ['field' => 'meta_description']) ?>
        </label>
        <label class="<?= $err('slug') ?>">URL <span class="hint">(/blog/…; empty = made from the title. Changing it on a live post breaks old links.)</span>
          <input name="slug" value="<?= e($v('slug')) ?>" maxlength="220" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="made-from-the-title">
          <?= $this->insert('partials/field_error', ['field' => 'slug']) ?>
        </label>
        <label class="<?= $err('job_category_id') ?>">Related jobs <span class="hint">(open jobs from this category are listed under the article)</span>
          <select name="job_category_id">
            <option value="">None</option>
            <?php foreach ($categories as $category): ?>
              <option value="<?= e($category->id) ?>"<?= (string) $v('job_category_id') === (string) $category->id ? ' selected' : '' ?>><?= e($category->name) ?></option>
            <?php endforeach; ?>
          </select>
          <?= $this->insert('partials/field_error', ['field' => 'job_category_id']) ?>
        </label>
      </section>
    </aside>
  </div>
</form>

<?php if ($post !== null): ?>
  <form class="danger-zone" method="post" action="/admin/blog/<?= e($post->id) ?>/delete" data-confirm="Delete the post “<?= e($post->title) ?>”? This can't be undone.">
    <?= $this->insert('partials/csrf') ?>
    <button class="btn-danger" type="submit">Delete post</button>
  </form>
<?php endif; ?>
