<?php
/** @var string $content */
/** @var \App\Domain\SiteSettings $site */
/** @var string|null $pageTitle */
/** @var string|null $metaDescription */
/** @var string|null $canonical */
/** @var string|null $head extra head markup captured by the page (JSON-LD, OpenGraph) */
/** @var string|null $robots */
$fullTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' · ' . $site->siteName : $site->siteName . ': ' . $site->tagline;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($fullTitle) ?></title>
  <meta name="description" content="<?= e($metaDescription ?? $site->metaDescription) ?>">
  <?php if (isset($canonical)): ?><link rel="canonical" href="<?= e($canonical) ?>"><?php endif; ?>
  <?php if (isset($robots)): ?><meta name="robots" content="<?= e($robots) ?>"><?php endif; ?>
  <meta property="og:site_name" content="<?= e($site->siteName) ?>">
  <meta property="og:type" content="<?= e($ogType ?? 'website') ?>">
  <meta property="og:title" content="<?= e($fullTitle) ?>">
  <meta property="og:description" content="<?= e($metaDescription ?? $site->metaDescription) ?>">
  <meta property="og:locale" content="en_UG">
  <?php if (isset($canonical)): ?><meta property="og:url" content="<?= e($canonical) ?>"><?php endif; ?>
  <meta property="og:image" content="<?= e(($appUrl ?? '') . $site->logoPath()) ?>">
  <meta name="twitter:card" content="summary">
  <?= $this->insert('partials/favicons') ?>
  <link rel="alternate" type="application/rss+xml" title="<?= e($site->siteName) ?> latest jobs" href="/jobs.rss">
  <?= $head ?? '' ?>
  <link rel="stylesheet" href="/assets/vendor/fontawesome/css/fontawesome.min.css">
  <link rel="stylesheet" href="/assets/vendor/fontawesome/css/brands.min.css">
  <link rel="stylesheet" href="/assets/vendor/fontawesome/css/solid.min.css">
  <link rel="stylesheet" href="/assets/css/site.css">
  <script src="/assets/js/js-flag.js"></script>
  <script src="/assets/js/site.js" defer></script>
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>
  <header class="topbar">
    <div class="container topbar-inner">
      <a href="/" class="brand" aria-label="<?= e($site->siteName) ?> home">
        <img src="<?= e($site->logoPath()) ?>" alt="<?= e($site->siteName) ?>" class="brand-logo" width="134" height="48">
      </a>
      <nav class="topnav" aria-label="Main">
        <a href="/cv-help"<?= ($currentPath ?? '') === '/cv-help' ? ' class="active" aria-current="page"' : '' ?>>CV help</a>
        <a href="/about"<?= ($currentPath ?? '') === '/about' ? ' class="active" aria-current="page"' : '' ?>>About</a>
        <a href="/contact"<?= ($currentPath ?? '') === '/contact' ? ' class="active" aria-current="page"' : '' ?>>Contact</a>
        <a href="/saved" id="savedLink">Saved <span id="savedCount" class="badge">0</span></a>
      </nav>
    </div>
  </header>

  <?= $content ?>

  <?= $this->insert('partials/public_footer') ?>
</body>
</html>
