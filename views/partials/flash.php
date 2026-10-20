<?php
/** @var \App\Core\Session\Session|null $session */
if ($session === null) {
    return;
}
foreach (['success', 'error', 'info'] as $type):
    $message = $session->getFlash($type);
    if (is_string($message) && $message !== ''): ?>
  <div class="flash <?= e($type) ?>" role="<?= $type === 'error' ? 'alert' : 'status' ?>"><?= e($message) ?></div>
<?php endif;
endforeach;
