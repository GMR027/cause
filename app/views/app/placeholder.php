<?php

$phase = (int) ($phase ?? 4);
$backRoute = $backRoute ?? '/inicio';
?>
<div class="page-heading">
    <div>
        <h1 class="h3 mb-1"><?= e($placeholderTitle ?? $title ?? '') ?></h1>
        <p class="text-secondary mb-0"><?= e(__('nav.soon_phase', [':phase' => (string) $phase])) ?></p>
    </div>
</div>

<div class="surface-card">
    <div class="empty-state">
        <span class="empty-icon" aria-hidden="true"><i class="bi bi-hourglass-split"></i></span>
        <h2 class="h5 mb-1"><?= e(__('placeholder.title')) ?></h2>
        <p class="text-secondary mb-3"><?= e(__('placeholder.body', [':phase' => (string) $phase])) ?></p>
        <a href="<?= e($backRoute) ?>" class="btn btn-outline-secondary">
            <?= e(__('common.actions.back')) ?>
        </a>
    </div>
</div>
