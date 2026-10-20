<?php
/** @var bool $formAvailable */
/** @var \App\Domain\SiteSettings $site */
use App\Core\Format;

$this->layout('layouts/public');
$price = Format::money($site->cvServicePrice);
$this->set('pageTitle', 'CV help: CV fix service in Uganda, ' . $price);
$this->set('metaDescription', "Get your CV reviewed and fixed by {$site->siteName} for {$price}. Clear structure, stronger wording, tailored to jobs in Uganda. Request online or on WhatsApp.");
$this->set('canonical', $appUrl . '/cv-help');
$this->start('head');
foreach ($jsonLd as $json): ?>
  <script type="application/ld+json"><?= $json ?></script>
<?php endforeach;
$this->end();
?>
<main class="container contact-page" id="main">
  <p class="crumbs"><a href="/">All jobs</a> › CV help</p>
  <h1>Get help with your CV</h1>
  <?= $this->insert('partials/flash') ?>

  <section class="cv-offer panel-card">
    <div>
      <h2>CV fix service</h2>
      <p>A strong CV gets you more interviews. We review your CV and fix it so it presents your experience clearly to Ugandan employers.</p>
      <ul class="cv-points">
        <li>Clear structure and layout that’s easy to scan</li>
        <li>Stronger wording for your experience and achievements</li>
        <li>Spelling, grammar and consistency checked</li>
        <li>Tailored to the kind of jobs you’re applying for</li>
      </ul>
    </div>
    <div class="cv-price">
      <span class="muted">Price</span>
      <strong><?= e($price) ?></strong>
      <span class="muted small">per CV</span>
    </div>
  </section>

  <div class="contact-grid">
    <section class="panel-card">
      <h2>Request CV help</h2>
      <p class="muted">Tell us about yourself and the jobs you’re targeting. We’ll reply with the next steps, including how to send us your CV.</p>
      <?php if ($formAvailable): ?>
        <?= $this->insert('partials/contact_form', [
            'messageLabel' => 'What do you need help with?',
            'messagePlaceholder' => 'e.g. I’m an accountant with 3 years’ experience applying for senior roles…',
            'submitLabel' => 'Request CV help',
        ]) ?>
      <?php else: ?>
        <p class="muted">The request form isn’t available right now.<?= $site->hasWhatsapp() ? ' Please reach us on WhatsApp.' : ' Please check back soon.' ?></p>
      <?php endif; ?>
    </section>

    <?= $this->insert('partials/whatsapp_card', [
        'whatsappIntro' => 'You can also send us your CV and questions directly on WhatsApp.',
        'whatsappMessage' => "Hello {$site->siteName}, I'd like help with my CV ({$price}).",
    ]) ?>
  </div>

  <section class="panel-card faq" aria-labelledby="faq-title">
    <h2 id="faq-title">Frequently asked questions</h2>
    <?php foreach ($faq as $item): ?>
      <details>
        <summary><?= e($item['question']) ?></summary>
        <p><?= e($item['answer']) ?></p>
      </details>
    <?php endforeach; ?>
  </section>
</main>
