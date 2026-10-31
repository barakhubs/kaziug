<?php
/** @var list<\App\Services\NewsletterTemplates\NewsletterTemplateInterface> $templates */
/** @var list<\App\Domain\Job> $jobs */
/** @var int $preselected */
/** @var string|null $error */
use App\Core\Format;
use App\Services\NewsletterTemplates\NewsletterTemplateInterface;

$this->layout('layouts/admin');
$this->set('title', 'New newsletter');
?>
<header class="page-head">
  <h1>New newsletter</h1>
  <div class="head-actions">
    <a class="btn-ghost" href="/admin/newsletters">← Back</a>
  </div>
</header>

<?php if ($error !== null): ?>
  <p class="flash error" role="alert"><?= e($error) ?></p>
<?php endif; ?>
<p class="muted">Pick a starting point. It fills in the subject and body for you; you can change everything before saving, with a live preview.</p>

<div class="template-grid">
  <?php foreach ($templates as $template): ?>
    <form class="form-card template-card" method="post" action="/admin/newsletters/new">
      <?= $this->insert('partials/csrf') ?>
      <input type="hidden" name="template" value="<?= e($template->key()) ?>">
      <h2><?= e($template->name()) ?></h2>
      <p class="muted"><?= e($template->description()) ?></p>

      <?php if ($template->jobSelection() !== NewsletterTemplateInterface::JOBS_NONE): ?>
        <?php if ($jobs === []): ?>
          <p class="hint">There are no live jobs to include yet.</p>
        <?php else: ?>
          <?php $many = $template->jobSelection() === NewsletterTemplateInterface::JOBS_MANY; ?>
          <fieldset class="job-pick">
            <legend><?= $many ? 'Jobs to include (newest first)' : 'Job to feature' ?></legend>
            <?php foreach ($jobs as $i => $job): ?>
              <label class="check">
                <input type="<?= $many ? 'checkbox' : 'radio' ?>" name="jobs[]" value="<?= e($job->id) ?>"<?= ($many ? $i < $preselected : $i === 0) ? ' checked' : '' ?>>
                <span><strong><?= e($job->title) ?></strong>
                  <span class="muted small">· <?= e($job->company) ?> · <?= e(Format::posted($job->publishedAt)) ?><?= $job->expiresAt !== null ? ' · closes ' . e(Format::date($job->expiresAt)) : '' ?></span></span>
              </label>
            <?php endforeach; ?>
          </fieldset>
        <?php endif; ?>
      <?php endif; ?>

      <div class="form-actions">
        <button class="btn-primary" type="submit"<?= $template->jobSelection() !== NewsletterTemplateInterface::JOBS_NONE && $jobs === [] ? ' disabled' : '' ?>>Use this template</button>
      </div>
    </form>
  <?php endforeach; ?>
</div>

<p><a href="/admin/newsletters/new?blank=1">Or start from a blank newsletter</a></p>
