<?php
/** @var array<string, string> $errors */
/** @var string $field */
if (isset($errors[$field])): ?>
<span class="field-error" id="<?= e($field) ?>-error"><?= e($errors[$field]) ?></span>
<?php endif;
