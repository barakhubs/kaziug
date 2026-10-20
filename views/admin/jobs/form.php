<?php
/** @var \App\Domain\Job|null $job */
/** @var array<string, mixed> $values */
/** @var array<string, string> $errors */
/** @var list<\App\Domain\Category> $categories */
/** @var list<array<string, mixed>> $history */
use App\Core\Format;
use App\Domain\ExperienceLevel;
use App\Domain\JobType;

$this->layout('layouts/admin');
$this->set('title', $job === null ? 'New job' : 'Edit job');
$v = static fn (string $key): mixed => $values[$key] ?? '';
$err = static fn (string $key): string => isset($errors[$key]) ? ' has-error' : '';
$action = $job === null ? '/admin/jobs' : "/admin/jobs/{$job->id}";
?>
<header class="page-head">
  <h1><?= $job === null ? 'New job' : 'Edit job' ?></h1>
  <div class="head-actions">
    <a class="btn-ghost" href="/admin/jobs">← All jobs</a>
    <?php if ($job !== null && $job->isPubliclyVisible()): ?>
      <a class="btn-ghost" href="/jobs/<?= e($job->slug) ?>" target="_blank" rel="noopener">View on site ↗</a>
    <?php endif; ?>
  </div>
</header>

<?php if ($errors !== []): ?>
  <div class="flash error" role="alert">Please fix the highlighted fields.</div>
<?php endif; ?>

<div class="edit-layout">
  <form class="form-card job-form-card" method="post" action="<?= e($action) ?>" novalidate>
    <?= $this->insert('partials/csrf') ?>
    <div class="grid2">
      <label class="full<?= $err('title') ?>">Job title
        <input name="title" value="<?= e($v('title')) ?>" required maxlength="200">
        <?= $this->insert('partials/field_error', ['field' => 'title']) ?>
      </label>
      <label class="<?= $err('company') ?>">Company
        <input name="company" value="<?= e($v('company')) ?>" required maxlength="200">
        <?= $this->insert('partials/field_error', ['field' => 'company']) ?>
      </label>
      <label class="<?= $err('location') ?>">Location (city)
        <input name="location" value="<?= e($v('location')) ?>" list="cityList" required maxlength="120">
        <?= $this->insert('partials/field_error', ['field' => 'location']) ?>
      </label>
      <label class="<?= $err('job_type') ?>">Job type
        <select name="job_type">
          <?php foreach (JobType::cases() as $type): ?>
            <option value="<?= e($type->value) ?>"<?= $v('job_type') === $type->value ? ' selected' : '' ?>><?= e($type->label()) ?></option>
          <?php endforeach; ?>
        </select>
        <?= $this->insert('partials/field_error', ['field' => 'job_type']) ?>
      </label>
      <label class="<?= $err('category_id') ?>">Category
        <select name="category_id" required>
          <option value="">Choose…</option>
          <?php foreach ($categories as $category): ?>
            <option value="<?= e($category->id) ?>"<?= (string) $v('category_id') === (string) $category->id ? ' selected' : '' ?>>
              <?= e($category->name) ?><?= $category->isActive ? '' : ' (inactive)' ?>
            </option>
          <?php endforeach; ?>
        </select>
        <?= $this->insert('partials/field_error', ['field' => 'category_id']) ?>
      </label>
      <label class="<?= $err('experience_level') ?>">Experience
        <select name="experience_level">
          <?php foreach (ExperienceLevel::cases() as $level): ?>
            <option value="<?= e($level->value) ?>"<?= $v('experience_level') === $level->value ? ' selected' : '' ?>><?= e($level->label()) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label class="<?= $err('expires_at') ?>">Expires on <span class="hint">(optional; hidden from the site after this date)</span>
        <input type="date" name="expires_at" value="<?= e($v('expires_at')) ?>">
        <?= $this->insert('partials/field_error', ['field' => 'expires_at']) ?>
      </label>
      <label class="<?= $err('salary_min') ?>">Salary min (per month)
        <input name="salary_min" inputmode="numeric" value="<?= e($v('salary_min')) ?>">
        <?= $this->insert('partials/field_error', ['field' => 'salary_min']) ?>
      </label>
      <label class="<?= $err('salary_max') ?>">Salary max (per month)
        <input name="salary_max" inputmode="numeric" value="<?= e($v('salary_max')) ?>">
        <?= $this->insert('partials/field_error', ['field' => 'salary_max']) ?>
      </label>
      <label class="<?= $err('currency') ?>">Currency
        <input name="currency" value="<?= e($v('currency') ?: 'UGX') ?>" maxlength="3" class="short">
        <?= $this->insert('partials/field_error', ['field' => 'currency']) ?>
      </label>
      <label class="check"><input type="checkbox" name="is_remote" value="1"<?= $v('is_remote') ? ' checked' : '' ?>> Remote-friendly</label>
      <label class="full<?= $err('skills') ?>">Skills (comma separated)
        <input name="skills" value="<?= e($v('skills')) ?>">
        <?= $this->insert('partials/field_error', ['field' => 'skills']) ?>
      </label>
      <label class="full<?= $err('summary') ?>">Summary
        <textarea name="summary" rows="3" required maxlength="2000"><?= e($v('summary')) ?></textarea>
        <?= $this->insert('partials/field_error', ['field' => 'summary']) ?>
      </label>
      <label class="full<?= $err('responsibilities') ?>">Responsibilities (one per line)
        <textarea name="responsibilities" rows="4"><?= e($v('responsibilities')) ?></textarea>
        <?= $this->insert('partials/field_error', ['field' => 'responsibilities']) ?>
      </label>
      <label class="full<?= $err('requirements') ?>">Requirements (one per line)
        <textarea name="requirements" rows="4"><?= e($v('requirements')) ?></textarea>
        <?= $this->insert('partials/field_error', ['field' => 'requirements']) ?>
      </label>
      <label class="<?= $err('apply_url') ?>">Application link <span class="hint">(optional)</span>
        <input type="url" name="apply_url" value="<?= e($v('apply_url')) ?>" placeholder="https://">
        <?= $this->insert('partials/field_error', ['field' => 'apply_url']) ?>
      </label>
      <label class="<?= $err('apply_email') ?>">Application email <span class="hint">(optional)</span>
        <input type="email" name="apply_email" value="<?= e($v('apply_email')) ?>">
        <?= $this->insert('partials/field_error', ['field' => 'apply_email']) ?>
      </label>
    </div>

    <div class="form-actions">
      <?php if ($job === null): ?>
        <button class="btn-primary" type="submit" name="intent" value="publish">Publish now</button>
        <button class="btn-ghost" type="submit" name="intent" value="draft">Save as draft</button>
      <?php else: ?>
        <button class="btn-primary" type="submit">Save changes</button>
      <?php endif; ?>
      <a class="btn-ghost" href="/admin/jobs">Cancel</a>
    </div>
  </form>

  <?php if ($job !== null): ?>
    <aside class="panel status-panel">
      <h2>Status</h2>
      <p><span class="pill <?= e($job->status->value) ?>"><?= e($job->status->label()) ?></span>
        <?php if ($job->isExpired()): ?><span class="pill closed">Expired</span><?php endif; ?></p>
      <?php if ($job->status->allowedNext() !== []): ?>
        <form method="post" action="/admin/jobs/<?= e($job->id) ?>/status" class="form-actions">
          <?= $this->insert('partials/csrf') ?>
          <?php foreach ($job->status->allowedNext() as $next): ?>
            <button class="btn-ghost btn-sm" type="submit" name="to" value="<?= e($next->value) ?>"><?= e($job->status->actionLabel($next)) ?></button>
          <?php endforeach; ?>
        </form>
      <?php else: ?>
        <p class="hint">Archived jobs can't change status.</p>
      <?php endif; ?>

      <h2 class="section-gap">History</h2>
      <ol class="timeline">
        <?php foreach ($history as $entry): ?>
          <li>
            <strong><?= e($entry['from_status'] === null ? 'Created as ' . $entry['to_status'] : $entry['from_status'] . ' → ' . $entry['to_status']) ?></strong>
            <div class="muted small"><?= e(Format::dbDateTime($entry['changed_at'])) ?><?= $entry['changed_by'] !== null ? ' · ' . e($entry['changed_by']) : '' ?></div>
          </li>
        <?php endforeach; ?>
      </ol>

      <div class="form-actions section-gap">
        <form method="post" action="/admin/jobs/<?= e($job->id) ?>/duplicate">
          <?= $this->insert('partials/csrf') ?>
          <button class="btn-ghost btn-sm" type="submit">Duplicate</button>
        </form>
        <form method="post" action="/admin/jobs/<?= e($job->id) ?>/delete" data-confirm="Delete this job? It will disappear from the site and the back office.">
          <?= $this->insert('partials/csrf') ?>
          <button class="btn-danger btn-sm" type="submit">Delete</button>
        </form>
      </div>
    </aside>
  <?php endif; ?>
</div>

<datalist id="cityList">
  <option value="Kampala"></option><option value="Entebbe"></option><option value="Jinja"></option>
  <option value="Mbarara"></option><option value="Gulu"></option><option value="Mukono"></option>
  <option value="Kasese"></option>
</datalist>
