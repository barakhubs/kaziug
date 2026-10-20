<?php
/** @var \App\Domain\Pagination $pagination */
/** @var string $path */
/** @var array<string, mixed> $query current filters, without page */
if ($pagination->pages <= 1) {
    return;
}
$url = static function (int $page) use ($path, $query): string {
    $params = array_filter([...$query, 'page' => $page > 1 ? $page : null], static fn ($v) => $v !== null && $v !== '' && $v !== 0);

    return $path . ($params === [] ? '' : '?' . http_build_query($params));
};
?>
<nav class="pager" aria-label="Pagination">
  <?php if ($pagination->hasPrevious()): ?>
    <a href="<?= e($url($pagination->page - 1)) ?>" rel="prev">‹ Prev</a>
  <?php else: ?>
    <span class="disabled">‹ Prev</span>
  <?php endif; ?>
  <?php foreach ($pagination->window() as $p): ?>
    <?php if ($p === null): ?>
      <span class="disabled">…</span>
    <?php elseif ($p === $pagination->page): ?>
      <span class="on" aria-current="page"><?= e($p) ?></span>
    <?php else: ?>
      <a href="<?= e($url($p)) ?>"><?= e($p) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
  <?php if ($pagination->hasNext()): ?>
    <a href="<?= e($url($pagination->page + 1)) ?>" rel="next">Next ›</a>
  <?php else: ?>
    <span class="disabled">Next ›</span>
  <?php endif; ?>
</nav>
