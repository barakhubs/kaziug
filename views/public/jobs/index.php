<?php
/** @var \App\Domain\JobSearchCriteria $criteria */
/** @var \App\Domain\Paginated<\App\Domain\Job> $result */
/** @var \App\Domain\Job|null $selected */
/** @var list<\App\Domain\Category> $categories */
/** @var list<string> $locations */
/** @var \App\Domain\Category|null $category */
/** @var string $basePath */
/** @var \App\Domain\SiteSettings $site */
/** @var \App\Core\Config $config */
use App\Domain\ExperienceLevel;
use App\Domain\JobType;

$this->layout('layouts/public');
$onCategoryPage = $category !== null;
$includeCategory = !$onCategoryPage;
$this->set('pageTitle', $onCategoryPage ? $category->name . ' jobs' : '');
if ($onCategoryPage && $category->description !== '') {
    $this->set('metaDescription', $category->description);
}
$this->set('canonical', $appUrl . ($basePath === '/' ? '/' : $basePath) . ($result->pagination->page > 1 ? '?page=' . $result->pagination->page : ''));
if ($criteria->hasFilters()) {
    // Filtered result pages are endless permutations: keep them out of search indexes.
    $this->set('robots', 'noindex, follow');
}

$paneUrl = static function (\App\Domain\Job $job) use ($criteria, $basePath, $includeCategory): string {
    return $basePath . '?' . http_build_query($criteria->toQuery(['job' => $job->slug], $includeCategory));
};
$total = $result->pagination->total;
?>
<section class="searchbar">
  <form id="jobsearch" class="container search-form" role="search" method="get" action="<?= e($basePath) ?>">
    <div class="field grow">
      <label for="q" class="sr-only">Job title, skill or company</label>
      <input id="q" name="q" type="search" value="<?= e($criteria->q) ?>" placeholder="Job title, skill or company" autocomplete="off" maxlength="100">
    </div>
    <div class="field">
      <label for="locInput" class="sr-only">Location</label>
      <input id="locInput" name="loc" type="text" list="locations" value="<?= e($criteria->location) ?>" placeholder="City or remote" autocomplete="off" maxlength="80">
      <datalist id="locations">
        <option value="Remote"></option>
        <?php foreach ($locations as $location): ?><option value="<?= e($location) ?>"></option><?php endforeach; ?>
      </datalist>
    </div>
    <button type="submit" class="btn-primary">Find jobs</button>
  </form>
</section>

<?php if ($onCategoryPage): ?>
  <div class="container category-head">
    <p class="crumbs"><a href="/">All jobs</a> › <?= e($category->name) ?></p>
    <h1><?= e($category->name) ?> jobs</h1>
    <?php if ($category->description !== ''): ?><p class="muted"><?= e($category->description) ?></p><?php endif; ?>
  </div>
<?php endif; ?>

<main class="container layout" id="main">
  <aside class="filters" id="filters" aria-label="Filters">
    <div class="filters-head">
      <h2 id="filters-title">Filters</h2>
      <a class="link-btn" href="<?= e($basePath) ?>">Clear all</a>
      <button type="button" class="filters-close" data-filters-close aria-label="Close filters">✕</button>
    </div>

    <fieldset>
      <legend>Job type</legend>
      <?php foreach (JobType::cases() as $type): ?>
        <label class="check"><input type="checkbox" name="type[]" value="<?= e($type->value) ?>" form="jobsearch" data-autosubmit<?= $criteria->hasType($type) ? ' checked' : '' ?>> <?= e($type->label()) ?></label>
      <?php endforeach; ?>
    </fieldset>

    <?php if (!$onCategoryPage): ?>
      <fieldset>
        <legend><label for="categoryFilter">Category</label></legend>
        <select id="categoryFilter" name="category" form="jobsearch" data-autosubmit>
          <option value="">All categories</option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= e($cat->slug) ?>"<?= $criteria->categorySlug === $cat->slug ? ' selected' : '' ?>><?= e($cat->name) ?> (<?= e($cat->jobCount) ?>)</option>
          <?php endforeach; ?>
        </select>
      </fieldset>
    <?php endif; ?>

    <fieldset>
      <legend><label for="expFilter">Experience</label></legend>
      <select id="expFilter" name="exp" form="jobsearch" data-autosubmit>
        <option value="">Any level</option>
        <?php foreach (ExperienceLevel::cases() as $level): ?>
          <option value="<?= e($level->value) ?>"<?= $criteria->experience === $level ? ' selected' : '' ?>><?= e($level->label()) ?></option>
        <?php endforeach; ?>
      </select>
    </fieldset>

    <fieldset>
      <legend><label for="dateFilter">Date posted</label></legend>
      <select id="dateFilter" name="posted" form="jobsearch" data-autosubmit>
        <option value="">Any time</option>
        <?php foreach ([1 => 'Last 24 hours', 7 => 'Last 7 days', 30 => 'Last 30 days'] as $days => $label): ?>
          <option value="<?= e($days) ?>"<?= $criteria->postedWithinDays === $days ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </fieldset>

    <fieldset>
      <legend>Salary (UGX / month)</legend>
      <div class="salary-range">
        <label><span class="sr-only">Minimum salary</span><input type="text" inputmode="numeric" name="salary_min" form="jobsearch" placeholder="Min" value="<?= e($criteria->salaryMin) ?>"></label>
        <span aria-hidden="true">–</span>
        <label><span class="sr-only">Maximum salary</span><input type="text" inputmode="numeric" name="salary_max" form="jobsearch" placeholder="Max" value="<?= e($criteria->salaryMax) ?>"></label>
      </div>
    </fieldset>

    <fieldset>
      <legend>Work style</legend>
      <label class="check"><input type="checkbox" name="remote" value="1" form="jobsearch" data-autosubmit<?= $criteria->remoteOnly ? ' checked' : '' ?>> Remote only</label>
    </fieldset>

    <div class="filters-foot">
      <button type="submit" class="btn-ghost apply-filters" form="jobsearch">
        <span class="label-wide">Apply filters</span><span class="label-panel">Show results</span>
      </button>
    </div>
  </aside>
  <div class="filters-backdrop" data-filters-close hidden></div>

  <section class="results" aria-labelledby="resultsSummary">
    <div class="results-head">
      <?php $activeFilters = $criteria->activeFilterCount($includeCategory); ?>
      <button type="button" class="filters-toggle" aria-controls="filters" aria-expanded="false">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 5h18v2H3V5Zm4 6h10v2H7v-2Zm3 6h4v2h-4v-2Z"/></svg>
        Filters<?php if ($activeFilters > 0): ?> <span class="badge"><?= e($activeFilters) ?></span><?php endif; ?>
      </button>
      <p id="resultsSummary" aria-live="polite">
        <strong><?= e($total) ?></strong> job<?= $total === 1 ? '' : 's' ?>
        <?php if ($criteria->q !== ''): ?> for “<?= e($criteria->q) ?>”<?php endif; ?>
        <?php if ($criteria->location !== ''): ?> in <?= e($criteria->location) ?><?php endif; ?>
      </p>
      <label>Sort by
        <select name="sort" form="jobsearch" data-autosubmit aria-label="Sort by">
          <?php foreach (['relevance' => 'Relevance', 'date' => 'Date posted', 'salary' => 'Salary'] as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= $criteria->sort === $value ? ' selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
    </div>

    <ul id="jobList" class="job-list">
      <?php if ($result->items === []): ?>
        <li class="empty">No jobs match these filters. Try removing a filter or broadening your search.</li>
      <?php endif; ?>
      <?php foreach ($result->items as $job): ?>
        <?= $this->insert('partials/job_card', ['job' => $job, 'active' => $selected?->id === $job->id, 'paneUrl' => $paneUrl($job)]) ?>
      <?php endforeach; ?>
    </ul>

    <?= $this->insert('partials/public_pager', [
        'pagination' => $result->pagination, 'criteria' => $criteria, 'basePath' => $basePath, 'includeCategory' => $includeCategory,
    ]) ?>
  </section>

  <article class="detail" id="detail" aria-label="Job details">
    <?php if ($selected !== null): ?>
      <?= $this->insert('partials/job_detail', ['job' => $selected, 'inPane' => true]) ?>
    <?php else: ?>
      <div class="detail-empty">Select a job to see its details.</div>
    <?php endif; ?>
  </article>
</main>
