<?php
$appName = $config['name'] ?? 'Cauce';
?>
<section class="py-5">
    <div class="alert alert-danger d-flex align-items-start gap-2" role="alert">
        <span aria-hidden="true">❌</span>
        <div>
            <h1 class="h4 alert-heading"><?= e(__('common.errors.500.title')) ?></h1>
            <p class="mb-0"><?= e($message ?? __('common.errors.500.message')) ?></p>
        </div>
    </div>

    <?php if (!empty($trace)): ?>
        <pre class="bg-dark text-light p-3 rounded-3 small overflow-auto"><?= e($trace) ?></pre>
    <?php endif; ?>

    <a href="/" class="btn btn-outline-secondary"><?= e(__('common.home.title')) ?></a>
    <p class="small text-secondary mt-3 mb-0">
        <?= e($appName) ?> · storage/logs/app.log
    </p>
</section>
