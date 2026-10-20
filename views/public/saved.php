<?php
/** @var list<\App\Domain\Job> $jobs */
/** @var bool $requested */
$this->layout('layouts/public');
$this->set('pageTitle', 'Saved jobs');
$this->set('robots', 'noindex');
?>
<main class="container saved-page" id="main">
  <p class="crumbs"><a href="/">All jobs</a> › Saved</p>
  <h1>Saved jobs</h1>
  <p class="muted">Saved jobs are stored in this browser only. Nobody else can see them.</p>
  <noscript><p class="empty">Saving jobs needs JavaScript. Turn it on to save and view jobs here.</p></noscript>

  <ul class="job-list" id="savedList" data-saved-page>
    <?php if ($jobs === []): ?>
      <li class="empty"><?= $requested ? 'None of your saved jobs are still open.' : 'You haven’t saved any jobs yet. Tap ☆ on a job to save it.' ?></li>
    <?php endif; ?>
    <?php foreach ($jobs as $job): ?>
      <?= $this->insert('partials/job_card', ['job' => $job, 'active' => false, 'paneUrl' => '/jobs/' . $job->slug]) ?>
    <?php endforeach; ?>
  </ul>
</main>
