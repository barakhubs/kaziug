<?php
/** @var bool $formAvailable */
/** @var \App\Domain\SiteSettings $site */
$this->layout('layouts/public');
$this->set('pageTitle', 'Contact us');
$this->set('metaDescription', 'Get in touch with ' . $site->siteName . ', the job board for Uganda, by message or WhatsApp.');
$this->set('canonical', $appUrl . '/contact');
?>
<main class="container contact-page" id="main">
  <p class="crumbs"><a href="/">All jobs</a> › Contact us</p>
  <h1>Contact us</h1>
  <?= $this->insert('partials/flash') ?>

  <div class="contact-grid">
    <section class="panel-card">
      <h2>Send us a message</h2>
      <?php if ($formAvailable): ?>
        <?= $this->insert('partials/contact_form', [
            'messageLabel' => 'Message',
            'messagePlaceholder' => 'How can we help?',
            'submitLabel' => 'Send message',
        ]) ?>
      <?php else: ?>
        <p class="muted">Our contact form isn’t available right now.<?= $site->hasWhatsapp() ? ' Please reach us on WhatsApp.' : ' Please check back soon.' ?></p>
      <?php endif; ?>
    </section>

    <?= $this->insert('partials/whatsapp_card', [
        'whatsappIntro' => 'Prefer chatting? Send us a WhatsApp message and we’ll reply as soon as we can.',
        'whatsappMessage' => "Hello {$site->siteName}, I have a question.",
    ]) ?>
  </div>
</main>
