<?php
/** @var string $state */
/** @var \App\Domain\SiteSettings $site */
$this->layout('layouts/public');
$this->set('robots', 'noindex');
$titles = [
    'form' => 'Subscribe to job alerts',
    'pending' => 'Check your inbox',
    'rate_limited' => 'Please try again later',
    'confirm' => 'Confirm your subscription',
    'confirmed' => 'You’re subscribed',
    'invalid' => 'This link doesn’t work',
    'unsubscribe' => 'Unsubscribe',
    'unsubscribed' => 'You’ve been unsubscribed',
];
$this->set('pageTitle', $titles[$state] ?? 'Job alerts');
if ($state === 'form') {
    // The form is in the page body; don't repeat it in the footer.
    $this->set('subscribeForm', '');
}
?>
<main class="container page" id="main">
  <div class="panel-card">
    <h1><?= e($titles[$state] ?? 'Job alerts') ?></h1>

    <?php if ($state === 'form'): ?>
      <p class="muted">Please check the form below.</p>
      <?= $this->insert('partials/subscribe_form') ?>

    <?php elseif ($state === 'pending'): ?>
      <p>If that address can receive job alerts, we’ve sent it an email with a confirmation link. Click it to finish subscribing.</p>
      <p class="muted">Nothing arrived after a few minutes? Check your spam folder, or subscribe again.</p>

    <?php elseif ($state === 'rate_limited'): ?>
      <p>Too many subscription requests came from your network. Please try again in about <?= e($minutes ?? 15) ?> minute(s).</p>

    <?php elseif ($state === 'confirm'): ?>
      <p>Click the button to start receiving job alerts from <?= e($site->siteName) ?>.</p>
      <form method="post" action="/subscribe/confirm/<?= e($token) ?>">
        <?= $this->insert('partials/csrf') ?>
        <button class="btn-primary" type="submit">Confirm subscription</button>
      </form>

    <?php elseif ($state === 'confirmed'): ?>
      <p>Thanks! You’ll now receive job alerts from <?= e($site->siteName) ?>. Every email has a link to unsubscribe.</p>
      <p><a href="/">Browse the latest jobs</a></p>

    <?php elseif ($state === 'invalid'): ?>
      <p>The link is invalid or has expired. Confirmation links work for 48 hours.</p>
      <p>You can <a href="/#subscribe">subscribe again</a> from the bottom of any page.</p>

    <?php elseif ($state === 'unsubscribe'): ?>
      <p>Stop sending job alerts to <strong><?= e($email) ?></strong>?</p>
      <form method="post" action="/unsubscribe/<?= e($token) ?>">
        <?= $this->insert('partials/csrf') ?>
        <button class="btn-primary" type="submit">Unsubscribe</button>
      </form>

    <?php elseif ($state === 'unsubscribed'): ?>
      <p>You won’t receive any more job alerts from <?= e($site->siteName) ?>.</p>
      <p><a href="/">Back to job listings</a></p>
    <?php endif; ?>
  </div>
</main>
