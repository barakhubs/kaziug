<?php
/** @var int $status */
/** @var string $message */
/** @var string|null $debugDetail */
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex">
  <title><?= e($status) ?> · KaziUG</title>
  <?= $this->insert('partials/favicons') ?>
  <link rel="stylesheet" href="/assets/css/site.css">
</head>
<body>
  <main class="container error-page">
    <p class="error-code"><?= e($status) ?></p>
    <h1><?= e($message) ?></h1>
    <p><a href="/">Back to job listings</a></p>
    <?php if ($debugDetail !== null): ?>
      <pre class="debug-trace"><?= e($debugDetail) ?></pre>
    <?php endif; ?>
  </main>
</body>
</html>
