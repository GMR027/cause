<?php
?>
<section class="text-center py-5">
    <p class="display-1 mb-2" aria-hidden="true">404</p>
    <h1 class="h3 mb-2"><?= e(__('common.errors.404.title')) ?></h1>
    <p class="text-secondary mb-4">
        <?= e(__('common.errors.404.message')) ?>
        <?php if (!empty($path)): ?>
            <br><code><?= e($path) ?></code>
        <?php endif; ?>
    </p>
    <a href="/" class="btn btn-primary"><?= e(__('common.home.title')) ?></a>
</section>
