<?php
/** @var string $content */
/** @var \App\Domain\SiteSettings $site */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title>Back office sign in · <?= e($site->siteName) ?></title>
  <?= $this->insert('partials/favicons') ?>
  <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
  <section class="auth">
    <div class="auth-wrap">
      <img class="auth-logo" src="<?= e($site->logoPath()) ?>" alt="<?= e($site->siteName) ?>" width="200" height="72">
      <?= $content ?>
    </div>
  </section>
</body>
</html>
