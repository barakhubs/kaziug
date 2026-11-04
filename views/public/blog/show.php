<?php
/** @var \App\Domain\BlogPost $post */
/** @var list<\App\Domain\BlogPost> $relatedPosts */
/** @var list<\App\Domain\Job> $relatedJobs */
/** @var list<string> $jsonLd */
/** @var string $appUrl */
use App\Core\Format;

$this->layout('layouts/public');
$this->set('pageTitle', $post->title);
$this->set('metaDescription', Format::excerpt($post->description()));
$this->set('canonical', $appUrl . '/blog/' . $post->slug);
$this->set('ogType', 'article');
if ($post->coverUrl() !== null) {
    $this->set('ogImage', $post->coverUrl());
    $this->set('ogImageAlt', $post->coverAlt !== '' ? $post->coverAlt : $post->title);
}
$this->start('head');
?>
  <meta property="article:published_time" content="<?= e($post->publishedAt?->format(DATE_ATOM)) ?>">
  <meta property="article:modified_time" content="<?= e($post->updatedAt->format(DATE_ATOM)) ?>">
  <link rel="alternate" type="application/rss+xml" title="Blog" href="/blog.rss">
<?php foreach ($jsonLd as $json): ?>
  <script type="application/ld+json"><?= $json ?></script>
<?php endforeach;
$this->end();
?>
<main class="container article-page" id="main">
  <p class="crumbs"><a href="/">All jobs</a> › <a href="/blog">Blog</a> › <?= e($post->title) ?></p>

  <div class="article-grid">
    <article class="article">
      <header>
        <h1><?= e($post->title) ?></h1>
        <p class="post-meta">
          <time datetime="<?= e($post->publishedAt?->format(DATE_ATOM)) ?>"><?= e(Format::date($post->publishedAt, 'j F Y')) ?></time>
          · <?= e($post->readingMinutes()) ?> min read
          <?php if ($post->updatedAt->format('Y-m-d') > $post->publishedAt?->format('Y-m-d')): ?>
            · Updated <time datetime="<?= e($post->updatedAt->format(DATE_ATOM)) ?>"><?= e(Format::date($post->updatedAt, 'j F Y')) ?></time>
          <?php endif; ?>
        </p>
      </header>
      <?php if ($post->coverUrl() !== null): ?>
        <img class="article-cover" src="<?= e($post->coverUrl()) ?>" alt="<?= e($post->coverAlt) ?>" width="1200" height="630" fetchpriority="high">
      <?php endif; ?>
      <?php /* bodyHtml was sanitised against an allowlist when it was saved (BlogService → HtmlSanitizer). */ ?>
      <div class="prose"><?= $post->bodyHtml ?></div>
    </article>

    <aside class="article-side" aria-label="More">
      <?php if ($relatedJobs !== []): ?>
        <section class="side-card">
          <h2><?= e($post->jobCategoryName) ?> jobs</h2>
          <ul class="related-jobs">
            <?php foreach ($relatedJobs as $job): ?>
              <li>
                <a class="related-title" href="/jobs/<?= e($job->slug) ?>"><?= e($job->title) ?></a>
                <span class="related-meta"><?= e($job->company) ?> · <?= e($job->location) ?></span>
                <span class="related-salary"><?= e(Format::jobSalary($job)) ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
          <p class="side-links"><a href="/categories/<?= e($post->jobCategorySlug) ?>">All <?= e($post->jobCategoryName) ?> jobs →</a></p>
        </section>
      <?php endif; ?>
      <section class="side-card cv-promo">
        <h2>Looking for work?</h2>
        <p>Browse every open vacancy in Uganda, updated daily.</p>
        <a class="btn-primary" href="/">See all jobs</a>
      </section>
    </aside>
  </div>

  <?php if ($relatedPosts !== []): ?>
    <section class="more-posts" aria-labelledby="morePosts">
      <h2 id="morePosts">More articles</h2>
      <ul class="post-grid">
        <?php foreach ($relatedPosts as $other): ?>
          <li class="post-card">
            <a class="post-card-link" href="/blog/<?= e($other->slug) ?>">
              <?php if ($other->coverUrl() !== null): ?>
                <img class="post-cover" src="<?= e($other->coverUrl()) ?>" alt="<?= e($other->coverAlt) ?>" width="1200" height="630" loading="lazy" decoding="async">
              <?php endif; ?>
              <div class="post-card-body">
                <h3><?= e($other->title) ?></h3>
                <p class="post-meta"><?= e(Format::date($other->publishedAt)) ?> · <?= e($other->readingMinutes()) ?> min read</p>
              </div>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    </section>
  <?php endif; ?>
</main>
