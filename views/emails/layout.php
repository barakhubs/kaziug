<?php
/** @var string $content already-safe HTML (escaped or sanitised by the caller) */
/** @var \App\Domain\SiteSettings $site */
/** @var string $preheader */
/** @var string|null $unsubscribeUrl */
/** @var string $appUrl */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($site->siteName) ?></title>
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Segoe UI,Roboto,Arial,sans-serif;color:#1c2430;">
  <span style="display:none;max-height:0;overflow:hidden;opacity:0;"><?= e($preheader) ?></span>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;">
    <tr><td align="center" style="padding:24px 12px;">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;background:#ffffff;border:1px solid #dfe3ea;border-radius:10px;">
        <tr><td style="background:#ffffff;padding:16px 24px;border-radius:10px 10px 0 0;border-bottom:4px solid #084f41;">
          <a href="<?= e($appUrl) ?>" style="color:#084f41;font-size:20px;font-weight:800;text-decoration:none;">
            <img src="<?= e($appUrl . $site->logoPath()) ?>" alt="<?= e($site->siteName) ?>" height="44" style="display:block;border:0;height:44px;width:auto;color:#084f41;font-size:20px;font-weight:800;">
          </a>
        </td></tr>
        <tr><td style="padding:24px;font-size:15px;line-height:1.55;">
          <?= $content ?>
        </td></tr>
        <tr><td style="padding:16px 24px;border-top:1px solid #dfe3ea;font-size:12px;line-height:1.5;color:#5d6675;">
          <?php if ($site->physicalAddress !== ''): ?>
            <p style="margin:0 0 8px;"><?= nl2br(e($site->physicalAddress)) ?></p>
          <?php endif; ?>
          <?php if ($unsubscribeUrl !== null): ?>
            <p style="margin:0;">You are receiving this because you subscribed to <?= e($site->siteName) ?> job alerts.
              <a href="<?= e($unsubscribeUrl) ?>" style="color:#0c6b58;">Unsubscribe</a> · <a href="<?= e($appUrl) ?>/privacy" style="color:#0c6b58;">Privacy Policy</a>.</p>
          <?php endif; ?>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
