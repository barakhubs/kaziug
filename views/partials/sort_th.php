<?php
/** @var \App\Domain\TableQuery $table */
/** @var string $key */
/** @var string $label */
/** @var string $path */
/** @var array<string, mixed> $filters current filters, kept in the link */
/** @var string $first direction used the first time this column is clicked */
$first ??= 'asc';
$active = $table->isSortedBy($key);
$params = array_filter([...$filters, ...$table->toQuery(['sort' => $key, 'dir' => $table->directionFor($key, $first), 'page' => null])], static fn ($v) => $v !== null && $v !== '' && $v !== 0);
$ariaSort = $active ? ($table->direction === 'asc' ? 'ascending' : 'descending') : 'none';
?>
<th aria-sort="<?= e($ariaSort) ?>"><a class="sort-link<?= $active ? ' on' : '' ?>" href="<?= e($path . '?' . http_build_query($params)) ?>"><?= e($label) ?><span class="sort-arrow" aria-hidden="true"><?= $active ? ($table->direction === 'asc' ? '▲' : '▼') : '↕' ?></span></a></th>
