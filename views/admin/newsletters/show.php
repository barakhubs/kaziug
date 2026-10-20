<?php
/** @var \App\Domain\Newsletter $newsletter */
/** @var \App\Domain\DeliveryCounts $counts */
/** @var list<array{email: string, attempts: int, last_error: ?string}> $failures */
/** @var bool $mailConfigured */
/** @var string $testTo */
/** @var \App\Domain\SiteSettings $site */
use App\Core\Format;
use App\Domain\NewsletterStatus;

$this->layout('layouts/admin');
$this->set('title', $newsletter->subject);
$status = $newsletter->status;
?>
<header class="page-head">
  <h1><?= e($newsletter->subject) ?></h1>
  <div class="head-actions">
    <a class="btn-ghost" href="/admin/newsletters">← All newsletters</a>
    <?php if ($status->isEditable()): ?><a class="btn-ghost" href="/admin/newsletters/<?= e($newsletter->id) ?>/edit">Edit</a><?php endif; ?>
  </div>
</header>

<?php if (!$mailConfigured): ?>
  <div class="flash error">Mail is not configured. <a href="/admin/settings/smtp">Add SMTP settings</a> before sending.</div>
<?php endif; ?>
<?php if ($site->physicalAddress === ''): ?>
  <div class="flash error">Every newsletter must include your physical address. <a href="/admin/settings">Add it in Site settings</a> before sending.</div>
<?php endif; ?>

<div class="stats">
  <div class="stat"><span>Status</span><strong><span class="pill <?= e($status->value) ?>"><?= e($status->label()) ?></span></strong></div>
  <div class="stat"><span>Queued</span><strong><?= e($counts->queued) ?></strong></div>
  <div class="stat"><span>Sent</span><strong><?= e($counts->sent) ?></strong></div>
  <div class="stat"><span>Failed</span><strong><?= e($counts->failed) ?></strong></div>
</div>

<div class="edit-layout">
  <section class="panel">
    <h2>Preview</h2>
    <p class="muted small">Exactly as subscribers will see it. Preheader: <?= e($newsletter->preheader !== '' ? $newsletter->preheader : '(none)') ?></p>
    <iframe class="email-preview" src="/admin/newsletters/<?= e($newsletter->id) ?>/preview" sandbox title="Email preview"></iframe>
  </section>

  <aside class="panel status-panel">
    <?php if ($newsletter->scheduledAt !== null && $status === NewsletterStatus::Scheduled): ?>
      <p><strong>Scheduled for <?= e(Format::dateTime($newsletter->scheduledAt)) ?></strong></p>
    <?php endif; ?>
    <?php if ($newsletter->sentAt !== null): ?>
      <p>Finished <?= e(Format::dateTime($newsletter->sentAt)) ?></p>
    <?php endif; ?>
    <?php if ($status === NewsletterStatus::Sending): ?>
      <p class="hint">Sending in the background. Refresh to see progress.</p>
    <?php endif; ?>

    <h2>Send a test</h2>
    <form method="post" action="/admin/newsletters/<?= e($newsletter->id) ?>/test" class="stack">
      <?= $this->insert('partials/csrf') ?>
      <label>To <input type="email" name="test_to" value="<?= e($testTo) ?>" required></label>
      <button class="btn-ghost btn-sm" type="submit"<?= $mailConfigured ? '' : ' disabled' ?>>Send test email</button>
      <span class="hint">Test emails have “[Test]” in the subject and a sample unsubscribe link.</span>
    </form>

    <?php if ($status->isEditable()): ?>
      <h2 class="section-gap">Send to subscribers</h2>
      <form method="post" action="/admin/newsletters/<?= e($newsletter->id) ?>/send" data-confirm="Send this newsletter to every active subscriber now?">
        <?= $this->insert('partials/csrf') ?>
        <button class="btn-primary" type="submit">Send now</button>
      </form>
      <form method="post" action="/admin/newsletters/<?= e($newsletter->id) ?>/schedule" class="stack section-gap">
        <?= $this->insert('partials/csrf') ?>
        <label>Or schedule for <span class="hint">(<?= e(date_default_timezone_get()) ?> time)</span>
          <input type="datetime-local" name="scheduled_at" value="<?= e($newsletter->scheduledAt?->format('Y-m-d\TH:i') ?? '') ?>" required>
        </label>
        <button class="btn-ghost btn-sm" type="submit"><?= $status === NewsletterStatus::Scheduled ? 'Reschedule' : 'Schedule' ?></button>
      </form>
      <?php if ($status === NewsletterStatus::Scheduled): ?>
        <form method="post" action="/admin/newsletters/<?= e($newsletter->id) ?>/unschedule" class="section-gap">
          <?= $this->insert('partials/csrf') ?>
          <button class="btn-ghost btn-sm" type="submit">Unschedule</button>
        </form>
      <?php else: ?>
        <form method="post" action="/admin/newsletters/<?= e($newsletter->id) ?>/delete" class="section-gap" data-confirm="Delete this draft?">
          <?= $this->insert('partials/csrf') ?>
          <button class="btn-danger btn-sm" type="submit">Delete draft</button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </aside>
</div>

<?php if ($failures !== []): ?>
  <section class="panel section-gap">
    <h2>Permanent failures (<?= e(count($failures)) ?>)</h2>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Subscriber</th><th>Attempts</th><th>Last error</th></tr></thead>
        <tbody>
        <?php foreach ($failures as $failure): ?>
          <tr><td><?= e($failure['email']) ?></td><td><?= e($failure['attempts']) ?></td><td class="small"><?= e($failure['last_error'] ?? '') ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
<?php endif; ?>
