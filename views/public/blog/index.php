<?php
/** @var \App\Domain\Paginated<\App\Domain\BlogPost> $result */
/** @var list<string> $jsonLd */
/** @var string $appUrl */
/** @var \App\Domain\SiteSettings $site */
use App\Core\Format;

$pagination = $result->pagination;
$this->layout('layouts/public');
$this->set('pageTitle', 'Career advice for job seekers in Uganda' . ($pagination->page > 1 ? " (page {$pagination->page})" : ''));
$this->set('metaDescription', "Practical career advice from {$site->siteName}: writing a CV, preparing for interviews and finding work in Uganda.");
$this->set('canonical', $appUrl . '/blog' . ($pagination->page > 1 ? '?page=' . $pagination->page : ''));
$this->start('head');
?>
  <link rel="alternate" type="application/rss+xml" title="<?= e($site->siteName) ?> blog" href="/blog.rss">
<?php foreach ($jsonLd as $json): ?>
  <script type="application/ld+json"><?= $json ?></script>
<?php endforeach;
$this->end();
$pageUrl = static fn (int $page): string => '/blog' . ($page > 1 ? '?page=' . $page : '');
?>
<main class="container blog-page" id="main">
  <p class="crumbs"><a href="/">All jobs</a> › Blog</p>
  <header class="blog-head">
    <h1>Career advice</h1>
    <p class="muted">Tips on CVs, interviews and finding work in Uganda.</p>
  </header>

  <?php if ($result->items === []): ?>
    <p class="muted">No articles yet. Check back soon.</p>
  <?php else: ?>
    <ul class="post-grid">
      <?php foreach ($result->items as $post): ?>
        <li class="post-card">
          <a class="post-card-link" href="/blog/<?= e($post->slug) ?>">
            <?php if ($post->coverUrl() !== null): ?>
              <img class="post-cover" src="<?= e($post->coverUrl()) ?>" alt="<?= e($post->coverAlt) ?>" width="1200" height="630" loading="lazy" decoding="async">
            <?php endif; ?>
            <div class="post-card-body">
              <h2><?= e($post->title) ?></h2>
              <p><?= e($post->excerpt !== '' ? $post->excerpt : $post->description()) ?></p>
              <p class="post-meta">
                <time datetime="<?= e($post->publishedAt?->format('Y-m-d')) ?>"><?= e(Format::date($post->publishedAt)) ?></time>
                · <?= e($post->readingMinutes()) ?> min read
              </p>
            </div>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>

  <?php if ($pagination->pages > 1): ?>
    <nav class="pager" aria-label="Blog pages">
      <?php if ($pagination->page > 1): ?><a href="<?= e($pageUrl($pagination->page - 1)) ?>" rel="prev">‹ Newer</a><?php endif; ?>
      <span>Page <?= e($pagination->page) ?> of <?= e($pagination->pages) ?></span>
      <?php if ($pagination->page < $pagination->pages): ?><a href="<?= e($pageUrl($pagination->page + 1)) ?>" rel="next">Older ›</a><?php endif; ?>
    </nav>
  <?php endif; ?>
</main>
