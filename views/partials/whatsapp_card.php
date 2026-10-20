<?php
/** @var \App\Domain\SiteSettings $site */
/** @var string $whatsappMessage pre-filled chat text */
/** @var string $whatsappIntro */
if (!$site->hasWhatsapp()) {
    return;
}
?>
<aside class="whatsapp-card">
  <h2>Chat on WhatsApp</h2>
  <p><?= e($whatsappIntro) ?></p>
  <p class="whatsapp-number"><?= e($site->whatsappDisplay()) ?></p>
  <a class="btn-whatsapp" href="<?= e($site->whatsappLink($whatsappMessage)) ?>" target="_blank" rel="noopener">
    <?= $this->insert('partials/icon', ['icon' => \App\Domain\SocialIcon::WhatsApp]) ?>
    Message us on WhatsApp
  </a>
</aside>
