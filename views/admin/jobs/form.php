<?php
/** @var \App\Domain\Job|null $job */
/** @var array<string, mixed> $values */
/** @var array<string, string> $errors */
/** @var list<\App\Domain\Category> $categories */

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
    <a class="btn-ghost" href="<?= e($job === null ? '/admin/jobs' : "/admin/jobs/{$job->id}") ?>">← <?= $job === null ? 'All jobs' : 'Job details' ?></a>
  </div>
</header>

<?php if ($errors !== []): ?>
  <div class="flash error" role="alert">Please fix the highlighted fields.</div>
<?php endif; ?>

<div>
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
      <label class="<?= $err('reports_to') ?>">Reports to <span class="hint">(optional, e.g. Consultant – Adult Cardiology)</span>
        <input name="reports_to" value="<?= e($v('reports_to')) ?>" maxlength="150">
        <?= $this->insert('partials/field_error', ['field' => 'reports_to']) ?>
      </label>
      <label class="<?= $err('expires_at') ?>">Application deadline <span class="hint">(applications close at the end of this day; the job then leaves the site)</span>
        <input type="date" name="expires_at" value="<?= e($v('expires_at')) ?>" required min="<?= e($job?->expiresAt !== null && $job->expiresAt < new DateTimeImmutable('today') ? $job->expiresAt->format('Y-m-d') : date('Y-m-d')) ?>">
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
      <label class="<?= $err('salary_scale') ?>">Salary scale <span class="hint">(government jobs, e.g. U4; use this or a salary amount, not both)</span>
        <input name="salary_scale" value="<?= e($v('salary_scale')) ?>" maxlength="30" class="short">
        <?= $this->insert('partials/field_error', ['field' => 'salary_scale']) ?>
      </label>
      <label class="<?= $err('positions') ?>">Number of positions
        <input type="number" name="positions" value="<?= e($v('positions') ?: '1') ?>" min="1" max="<?= e(\App\Validation\JobValidator::MAX_POSITIONS) ?>" class="short">
        <?= $this->insert('partials/field_error', ['field' => 'positions']) ?>
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
      <fieldset class="full apply-fieldset<?= $err('apply_method') ?>" data-apply-method>
        <legend>How to apply</legend>
        <p class="hint">Shown on the job page as the “Apply” buttons. Choose at least one.</p>
        <div class="choice-row">
          <?php foreach (['link' => 'Link (application page or more info)', 'email' => 'Email', 'both' => 'Link and email'] as $method => $label): ?>
            <label class="check"><input type="radio" name="apply_method" value="<?= e($method) ?>"<?= $v('apply_method') === $method ? ' checked' : '' ?>> <?= e($label) ?></label>
          <?php endforeach; ?>
        </div>
        <?= $this->insert('partials/field_error', ['field' => 'apply_method']) ?>
        <div class="grid2">
          <label class="<?= $err('apply_url') ?>" data-apply-field="link">Application / more-info link
            <input type="url" name="apply_url" value="<?= e($v('apply_url')) ?>" placeholder="https://company.example/careers/123">
            <?= $this->insert('partials/field_error', ['field' => 'apply_url']) ?>
          </label>
          <label class="<?= $err('apply_email') ?>" data-apply-field="email">Application email
            <input type="email" name="apply_email" value="<?= e($v('apply_email')) ?>" placeholder="jobs@company.example">
            <?= $this->insert('partials/field_error', ['field' => 'apply_email']) ?>
          </label>
        </div>
        <label class="<?= $err('application_instructions') ?>">Application instructions <span class="hint">(optional, plain text: documents to attach, where to deliver hard copies, etc.)</span>
          <textarea name="application_instructions" rows="4" maxlength="2000"><?= e($v('application_instructions')) ?></textarea>
          <?= $this->insert('partials/field_error', ['field' => 'application_instructions']) ?>
        </label>
      </fieldset>
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

</div>

<datalist id="cityList">
  <option value="Kampala"></option><option value="Entebbe"></option><option value="Jinja"></option>
  <option value="Mbarara"></option><option value="Gulu"></option><option value="Mukono"></option>
  <option value="Kasese"></option>
</datalist>
