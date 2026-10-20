<?php
/** @var \App\Domain\SiteSettings $site */
$this->layout('layouts/public');
$this->set('pageTitle', 'Back soon');
$this->set('robots', 'noindex');
?>
<main class="container page" id="main">
  <div class="panel-card">
    <h1>We’ll be back soon</h1>
    <p><?= e($site->siteName) ?> is undergoing maintenance. Please check back shortly.</p>
    <?php if ($site->contactEmail !== ''): ?>
      <p class="muted">Questions? Email <a href="mailto:<?= e($site->contactEmail) ?>"><?= e($site->contactEmail) ?></a>.</p>
    <?php endif; ?>
  </div>
</main>
