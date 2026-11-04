<?php
/** @var list<\App\Domain\BlogPost> $posts */
use App\Core\Format;

$this->layout('layouts/admin');
$this->set('title', 'Blog');
?>
<header class="page-head">
  <h1>Blog</h1>
  <div class="head-actions">
    <a class="btn-ghost" href="/blog" target="_blank" rel="noopener">View blog ↗</a>
    <a class="btn-primary" href="/admin/blog/new">+ New post</a>
  </div>
</header>
<p class="hint">Posts go live when published; give a future date to schedule one. A cover image is needed to publish.</p>

<div class="table-wrap">
  <table>
    <thead><tr><th><span class="sr-only">Cover</span></th><th>Title</th><th>Status</th><th>Date</th><th>Related jobs</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php if ($posts === []): ?>
      <tr><td colspan="6" class="muted center">No posts yet. Write the first one.</td></tr>
    <?php endif; ?>
    <?php foreach ($posts as $post): $status = $post->statusLabel(); ?>
      <tr>
        <td class="logo-cell">
          <?php if ($post->hasCover): ?>
            <img class="cover-thumb" src="<?= e($post->adminCoverUrl()) ?>" alt="" width="64" height="36" loading="lazy">
          <?php else: ?>
            <span class="pill draft">No cover</span>
          <?php endif; ?>
        </td>
        <td>
          <strong><a href="/admin/blog/<?= e($post->id) ?>/edit"><?= e($post->title) ?></a></strong>
          <div class="sub">/blog/<?= e($post->slug) ?> · <?= e($post->readingMinutes()) ?> min read</div>
        </td>
        <td><span class="pill <?= e(strtolower($status)) ?>"><?= e($status) ?></span></td>
        <td class="small nowrap"><?= e($post->publishedAt !== null ? Format::dateTime($post->publishedAt) : '—') ?></td>
        <td class="small"><?= e($post->jobCategoryName ?? '—') ?></td>
        <td class="row-actions">
          <?php if ($post->isLive()): ?><a class="row-link" href="/blog/<?= e($post->slug) ?>" target="_blank" rel="noopener">View</a><?php endif; ?>
          <a class="row-link" href="/admin/blog/<?= e($post->id) ?>/edit">Edit</a>
          <form class="inline" method="post" action="/admin/blog/<?= e($post->id) ?>/delete" data-confirm="Delete the post “<?= e($post->title) ?>”? This can't be undone.">
            <?= $this->insert('partials/csrf') ?>
            <button type="submit" class="danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
