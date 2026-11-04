<?php
/** @var string $content */
/** @var string $currentPath */
/** @var \App\Domain\AdminUser $admin */
/** @var string|null $title */

use App\Core\Icon;

// Sidebar groups: [heading or null, [[href, label, icon], ...]]. Icons are files in views/icons.
$navGroups = [
    [null, [['/admin', 'Dashboard', 'solid/gauge'], ['/admin/analytics', 'Analytics', 'solid/chart-line']]],
    ['Jobs', [
        ['/admin/jobs', 'Jobs', 'solid/briefcase'],
        ['/admin/categories', 'Categories', 'solid/tags'],
        ['/admin/companies', 'Companies', 'solid/building'],
    ]],
    ['Content', [['/admin/blog', 'Blog', 'solid/newspaper'], ['/admin/popups', 'Popups', 'solid/bullhorn']]],
    ['Email', [['/admin/newsletters', 'Newsletters', 'solid/envelope'], ['/admin/subscribers', 'Subscribers', 'solid/users']]],
    ['System', [
        ['/admin/settings', 'Settings', 'solid/gear'],
        ['/admin/account', 'Account', 'solid/user'],
        ['/admin/audit', 'Audit log', 'solid/clipboard-list'],
    ]],
];
$nav = array_merge(...array_column($navGroups, 1));
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
  <?= $this->insert('partials/favicons') ?>
  <?= $head ?? '' ?>
  <link rel="stylesheet" href="/assets/css/admin.css">
  <script src="/assets/js/admin.js" defer></script>
</head>
<body>
  <div class="app">
    <aside class="side">
      <a class="side-brand" href="/admin" aria-label="<?= e($site->siteName) ?> back office home">
        <span class="side-logo"><img src="<?= e($site->logoPath()) ?>" alt="<?= e($site->siteName) ?>" width="134" height="48"></span>
        <small>Back office</small>
      </a>
      <nav class="side-nav" aria-label="Back office">
        <a class="nav-quick" href="/admin/jobs/new"><?= Icon::svg('solid/plus') ?><span>New job</span></a>
        <?php foreach ($navGroups as $i => [$heading, $links]): ?>
          <div class="nav-group"<?= $heading !== null ? ' role="group" aria-labelledby="nav-group-' . $i . '"' : '' ?>>
            <?php if ($heading !== null): ?><p class="nav-heading" id="nav-group-<?= e($i) ?>"><?= e($heading) ?></p><?php endif; ?>
            <?php foreach ($links as [$href, $label, $icon]): ?>
              <a class="nav-btn<?= $isActive($href) ? ' active' : '' ?>" href="<?= e($href) ?>"<?= $isActive($href) ? ' aria-current="page"' : '' ?>><?= Icon::svg($icon) ?><span><?= e($label) ?></span></a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
        <a class="nav-btn" href="/" target="_blank" rel="noopener"><?= Icon::svg('solid/arrow-up-right-from-square') ?><span>View public site</span></a>
      </nav>
      <div class="logout">
        <p class="side-user"><?= e($admin->email) ?></p>
        <form method="post" action="/admin/logout">
          <?= $this->insert('partials/csrf') ?>
          <button class="nav-btn" type="submit"><?= Icon::svg('solid/right-from-bracket') ?><span>Sign out</span></button>
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
