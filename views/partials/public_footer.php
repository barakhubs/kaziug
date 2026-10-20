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
    </div>

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
