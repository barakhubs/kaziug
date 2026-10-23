<?php
/** @var \App\Domain\SiteSettings $site */
/** @var list<\App\Domain\SocialProfile> $socialProfiles */
$socialProfiles ??= [];
?>
<footer class="site-footer">
  <div class="container footer-inner">
    <div class="footer-about">
      <p class="footer-brand"><?= e($site->siteName) ?></p>
      <?php if ($site->footerText !== ''): ?><p><?= e($site->footerText) ?></p><?php endif; ?>
      <?php if ($site->physicalAddress !== ''): ?><p class="muted"><?= nl2br(e($site->physicalAddress)) ?></p><?php endif; ?>
      <?php if ($site->contactEmail !== ''): ?><p><a href="mailto:<?= e($site->contactEmail) ?>"><?= e($site->contactEmail) ?></a></p><?php endif; ?>
      <p class="footer-links"><a href="/about">About us</a> · <a href="/contact">Contact us</a> · <a href="/cv-help">CV help</a></p>
    </div>

    <?php if (($footerCities ?? []) !== [] || ($footerCategories ?? []) !== []): ?>
      <nav class="footer-browse" aria-label="Browse jobs">
        <?php if (($footerCities ?? []) !== []): ?>
          <div>
            <p class="footer-heading">Jobs by city</p>
            <ul><?php foreach ($footerCities as $fc): ?><li><a href="/jobs-in/<?= e($fc['slug']) ?>">Jobs in <?= e($fc['name']) ?></a></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>
        <?php if (($footerCategories ?? []) !== []): ?>
          <div>
            <p class="footer-heading">Jobs by category</p>
            <ul><?php foreach ($footerCategories as $fcat): ?><li><a href="/categories/<?= e($fcat->slug) ?>"><?= e($fcat->name) ?></a></li><?php endforeach; ?></ul>
          </div>
        <?php endif; ?>
      </nav>
    <?php endif; ?>

    <?php if (isset($subscribeForm) && $subscribeForm): ?>
      <?= $this->insert('partials/subscribe_form') ?>
    <?php endif; ?>

    <?php if ($socialProfiles !== []): ?>
      <ul class="social" aria-label="Follow us">
        <?php foreach ($socialProfiles as $profile): ?>
          <li>
            <a href="<?= e($profile->url) ?>" rel="noopener me" target="_blank" title="<?= e($profile->label) ?>">
              <?= $this->insert('partials/icon', ['icon' => $profile->icon]) ?>
              <span class="sr-only"><?= e($profile->label) ?></span>
            </a>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
  <div class="container footer-legal">© <?= e(date('Y')) ?> <?= e($site->siteName) ?></div>
</footer>
<div class="toast" role="status" aria-live="polite"></div>
