<?php
/** @var string $currentPath */
$tabs = [
    '/admin/settings' => 'General',
    '/admin/settings/social' => 'Social profiles',
    '/admin/settings/smtp' => 'Email (SMTP)',
];
$active = '/admin/settings';
foreach (array_keys($tabs) as $href) {
    if (($currentPath === $href || str_starts_with($currentPath, $href . '/')) && strlen($href) > strlen($active)) {
        $active = $href;
    }
}
?>
<header class="page-head"><h1>Settings</h1></header>
<nav class="tabs" aria-label="Settings sections">
  <?php foreach ($tabs as $href => $label): ?>
    <a class="tab<?= $href === $active ? ' on' : '' ?>" href="<?= e($href) ?>"<?= $href === $active ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
  <?php endforeach; ?>
</nav>
