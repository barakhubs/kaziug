<?php
/** @var \App\Domain\JobSearchCriteria $criteria */
/** @var \App\Domain\Paginated<\App\Domain\Job> $result */
/** @var \App\Domain\Job|null $selected */
/** @var list<\App\Domain\Category> $categories */
/** @var list<string> $locations */
/** @var list<array{name: string, slug: string, count: int}> $cities */
/** @var \App\Domain\Category|null $category */
/** @var array{name: string, slug: string, count: int}|null $city */
/** @var bool $includeCategory */
/** @var bool $includeLocation */
/** @var list<string> $jsonLd */
/** @var string $basePath */
/** @var string $appUrl */
/** @var \App\Domain\SiteSettings $site */
use App\Core\Format;
use App\Domain\ExperienceLevel;
use App\Domain\JobType;

$this->layout('layouts/public');
$total = $result->pagination->total;
$plural = static fn (int $n): string => $n === 1 ? '1 open job' : "{$n} open jobs";

if ($category !== null) {
    $this->set('pageTitle', $category->name . ' jobs in Uganda');
    $this->set('metaDescription', Format::excerpt($category->description !== ''
        ? $category->description
        : "Browse {$plural($total)} in {$category->name} across Uganda, with salaries in UGX and how to apply."));
} elseif ($city !== null) {
    $this->set('pageTitle', 'Jobs in ' . $city['name'] . ', Uganda');
    $this->set('metaDescription', Format::excerpt("Browse {$plural($city['count'])} in {$city['name']}, Uganda: full-time, part-time, contract and internship roles with salaries in UGX. Updated daily."));
}
$this->set('canonical', $appUrl . $basePath . ($result->pagination->page > 1 ? '?page=' . $result->pagination->page : ''));
if ($criteria->hasFilters($includeLocation)) {
    // Filtered result pages are endless permutations: keep them out of search indexes.
    $this->set('robots', 'noindex, follow');
}
$this->start('head');
foreach ($jsonLd as $json): ?>
  <script type="application/ld+json"><?= $json ?></script>
<?php endforeach;
$this->end();

$paneUrl = static function (\App\Domain\Job $job) use ($criteria, $basePath, $includeCategory, $includeLocation): string {
    return $basePath . '?' . http_build_query($criteria->toQuery(['job' => $job->slug], $includeCategory, $includeLocation));
};
$otherCities = array_values(array_filter($cities, static fn (array $c): bool => $c['slug'] !== ($city['slug'] ?? '')));
?>
<section class="searchbar">
  <?php if ($category === null && $city === null): ?>
    <div class="container"><h1 class="search-title"><?= e($site->tagline !== '' ? $site->tagline : 'Jobs in Uganda') ?></h1></div>
  <?php endif; ?>
  <form id="jobsearch" class="container search-form" role="search" method="get" action="<?= e($city !== null ? '/' : $basePath) ?>">
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

<?php if ($category !== null): ?>
  <div class="container category-head">
    <p class="crumbs"><a href="/">All jobs</a> › <?= e($category->name) ?></p>
    <h1><?= e($category->name) ?> jobs</h1>
    <p class="muted"><?= e($category->description !== '' ? $category->description : ucfirst($plural($total)) . " in {$category->name} across Uganda.") ?></p>
  </div>
<?php elseif ($city !== null): ?>
  <div class="container category-head">
    <p class="crumbs"><a href="/">All jobs</a> › <?= e($city['name']) ?></p>
    <h1>Jobs in <?= e($city['name']) ?></h1>
    <p class="muted"><?= e(ucfirst($plural($city['count']))) ?> in <?= e($city['name']) ?>, Uganda: full-time, part-time, contract and internship roles. Salaries are monthly, in UGX, where employers share them.</p>
    <?php if ($otherCities !== []): ?>
      <p class="city-links">Other cities:
        <?php foreach (array_slice($otherCities, 0, 8) as $i => $other): ?><?= $i > 0 ? ' · ' : '' ?><a href="/jobs-in/<?= e($other['slug']) ?>"><?= e($other['name']) ?></a><?php endforeach; ?>
      </p>
    <?php endif; ?>
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

    <?php if ($includeCategory): ?>
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
        <i class="fa-solid fa-sliders" aria-hidden="true"></i>
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
        'pagination' => $result->pagination, 'criteria' => $criteria, 'basePath' => $basePath, 'includeCategory' => $includeCategory, 'includeLocation' => $includeLocation,
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
