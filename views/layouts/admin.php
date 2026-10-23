<?php
/** @var string $content */
/** @var string $currentPath */
/** @var \App\Domain\AdminUser $admin */
/** @var string|null $title */

$nav = [
    ['/admin', 'Dashboard'],
    ['/admin/jobs', 'Jobs'],
    ['/admin/categories', 'Categories'],
    ['/admin/newsletters', 'Newsletters'],
    ['/admin/subscribers', 'Subscribers'],
    ['/admin/settings', 'Settings'],
    ['/admin/account', 'Account'],
    ['/admin/audit', 'Audit log'],
];
// The longest nav link that prefixes the current path is the active one.
$activeHref = '/admin';
foreach ($nav as [$href]) {
    if (($currentPath === $href || str_starts_with($currentPath, $href . '/')) && strlen($href) > strlen($activeHref)) {
        $activeHref = $href;
    }
}
$isActive = static fn (string $href): bool => $href === $activeHref;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow">
  <title><?= e(($title ?? 'Back office') . ' · KaziUG') ?></title>
  <link rel="stylesheet" href="/assets/vendor/fontawesome/css/fontawesome.min.css">
  <link rel="stylesheet" href="/assets/vendor/fontawesome/css/brands.min.css">
  <link rel="stylesheet" href="/assets/vendor/fontawesome/css/solid.min.css">
  <link rel="stylesheet" href="/assets/css/admin.css">
  <script src="/assets/js/admin.js" defer></script>
</head>
<body>
  <div class="app">
    <aside class="side">
      <div class="side-brand">Kazi<span>UG</span><small>Back office</small></div>
      <nav class="side-nav" aria-label="Back office">
        <?php foreach ($nav as [$href, $label]): ?>
          <a class="nav-btn<?= $isActive($href) ? ' active' : '' ?>" href="<?= e($href) ?>"<?= $isActive($href) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
        <a class="nav-btn" href="/" target="_blank" rel="noopener">View public site ↗</a>
      </nav>
      <div class="logout">
        <p class="side-user"><?= e($admin->email) ?></p>
        <form method="post" action="/admin/logout">
          <?= $this->insert('partials/csrf') ?>
          <button class="nav-btn" type="submit">Sign out</button>
        </form>
      </div>
    </aside>

    <main class="main" id="main">
      <?= $this->insert('partials/flash') ?>
      <?= $content ?>
    </main>
  </div>
</body>
</html>
