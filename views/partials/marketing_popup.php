<?php
/** @var \App\Domain\Popup $popup */
// Hidden until site.js opens it, and only if this browser hasn't seen this popup (and version) yet.
// bodyHtml was sanitised against an allowlist when it was saved (PopupService → HtmlSanitizer).
$imageUrl = $popup->imageUrl();
?>
<dialog class="promo" id="promo" aria-labelledby="promoTitle"
        data-promo-key="<?= e($popup->seenKey()) ?>" data-trigger="<?= e($popup->trigger->value) ?>" data-delay="<?= e($popup->delaySeconds) ?>">
  <button class="promo-close" type="button" data-promo-close aria-label="Close">✕</button>
  <?php if ($imageUrl !== null): ?>
    <img class="promo-image" src="<?= e($imageUrl) ?>" alt="<?= e($popup->imageAlt) ?>" loading="lazy" decoding="async">
  <?php endif; ?>
  <div class="promo-body">
    <h2 id="promoTitle"><?= e($popup->heading) ?></h2>
    <div class="promo-text"><?= $popup->bodyHtml ?></div>
    <?php if ($popup->buttonLabel !== null && $popup->buttonUrl !== null): ?>
      <a class="btn-primary promo-cta" href="<?= e($popup->buttonUrl) ?>" data-promo-close><?= e($popup->buttonLabel) ?></a>
    <?php endif; ?>
  </div>
</dialog>
