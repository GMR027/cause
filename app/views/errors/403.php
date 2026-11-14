<?php
$message = $message ?? __('common.errors.403.message');
?>
<section class="text-center py-5">
    <p class="display-1 mb-2" aria-hidden="true">403</p>
    <h1 class="h3 mb-2"><?= e(__('common.errors.403.title')) ?></h1>
    <p class="text-secondary mb-4"><?= e($message) ?></p>
    <a href="/" class="btn btn-primary"><?= e(__('common.home.title')) ?></a>
</section>
