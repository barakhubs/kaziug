<?php
/** @var list<\App\Domain\Company> $companies */
/** @var string $q */
$this->layout('layouts/admin');
$this->set('title', 'Companies');
?>
<header class="page-head">
  <h1>Companies</h1>
  <div class="head-actions"><a class="btn-primary" href="/admin/companies/new">+ New company</a></div>
</header>
<p class="hint">Companies with jobs can't be deleted (deleted jobs still count). Companies without a logo came from jobs added before logos were required: open them to upload one.</p>

<form class="toolbar" method="get" action="/admin/companies" role="search">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search companies…" aria-label="Search companies">
  <button class="btn-ghost" type="submit">Search</button>
  <?php if ($q !== ''): ?><a class="link-reset" href="/admin/companies">Clear</a><?php endif; ?>
</form>

<div class="table-wrap">
  <table>
    <thead><tr><th><span class="sr-only">Logo</span></th><th>Name</th><th>Website</th><th>Jobs</th><th><span class="sr-only">Actions</span></th></tr></thead>
    <tbody>
    <?php if ($companies === []): ?>
      <tr><td colspan="5" class="muted center"><?= $q === '' ? 'No companies yet.' : 'No companies match this search.' ?></td></tr>
    <?php endif; ?>
    <?php foreach ($companies as $company): ?>
      <tr>
        <td class="logo-cell">
          <?php if ($company->hasLogo): ?>
            <img class="company-thumb" src="<?= e($company->logoUrl()) ?>" alt="" width="40" height="40" loading="lazy">
          <?php else: ?>
            <span class="pill draft">No logo</span>
          <?php endif; ?>
        </td>
        <td><strong><?= e($company->name) ?></strong></td>
        <td class="small"><?php if ($company->website !== null): ?><a href="<?= e($company->website) ?>" target="_blank" rel="noopener"><?= e($company->website) ?></a><?php else: ?>—<?php endif; ?></td>
        <td><?= e($company->jobCount) ?></td>
        <td class="row-actions">
          <a class="row-link" href="/admin/companies/<?= e($company->id) ?>/edit">Edit</a>
          <form class="inline" method="post" action="/admin/companies/<?= e($company->id) ?>/delete" data-confirm="Delete the company “<?= e($company->name) ?>”?">
            <?= $this->insert('partials/csrf') ?>
            <button type="submit" class="danger">Delete</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
