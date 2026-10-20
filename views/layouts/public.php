<?php
/** @var string $content */
/** @var \App\Domain\SiteSettings $site */
/** @var string|null $pageTitle */
/** @var string|null $metaDescription */
/** @var string|null $canonical */
/** @var string|null $head extra head markup captured by the page (JSON-LD, OpenGraph) */
/** @var string|null $robots */
$fullTitle = isset($pageTitle) && $pageTitle !== '' ? $pageTitle . ' · ' . $site->siteName : $site->siteName . ': ' . $site->tagline;
$brandName = $site->siteName;
$brandAccent = '';
if (preg_match('/^(.+?)(UG)$/u', $brandName, $m) === 1) {
    [$brandName, $brandAccent] = [$m[1], $m[2]];
}
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
  <?= $head ?? '' ?>
  <link rel="stylesheet" href="/assets/css/site.css">
  <script src="/assets/js/site.js" defer></script>
</head>
<body>
  <a class="skip-link" href="#main">Skip to content</a>
  <header class="topbar">
    <div class="container topbar-inner">
      <a href="/" class="brand">
        <?php if ($site->hasLogo()): ?>
          <img src="/media/logo?v=<?= e($site->logoVersion) ?>" alt="<?= e($site->siteName) ?>" class="brand-logo">
        <?php else: ?>
          <?= e($brandName) ?><?php if ($brandAccent !== ''): ?><span><?= e($brandAccent) ?></span><?php endif; ?>
        <?php endif; ?>
      </a>
      <nav class="topnav" aria-label="Main">
        <a href="/saved" id="savedLink">Saved <span id="savedCount" class="badge">0</span></a>
      </nav>
    </div>
  </header>

  <?= $content ?>

  <?= $this->insert('partials/public_footer') ?>
</body>
</html>
