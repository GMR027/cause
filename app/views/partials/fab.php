<?php

$fabItems = (array) config('navigation.fab', []);
?>
<div class="app-fab-wrap d-xl-none">
    <button class="app-fab" type="button" data-bs-toggle="offcanvas" data-bs-target="#appSheet"
            aria-controls="appSheet" aria-label="<?= e(__('nav.registrar')) ?>">
        <i class="bi bi-plus-lg" aria-hidden="true"></i>
    </button>
</div>

<div class="offcanvas offcanvas-bottom app-sheet" tabindex="-1" id="appSheet"
     aria-labelledby="appSheetLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="appSheetLabel"><?= e(__('nav.registrar')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas"
                aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body pt-0">
        <div class="d-grid gap-2">
            <?php foreach ($fabItems as $item): ?>
                <a class="app-sheet-action" href="<?= e((string) $item['route']) ?>">
                    <span class="app-sheet-icon">
                        <i class="bi <?= e((string) $item['icon']) ?>" aria-hidden="true"></i>
                    </span>
                    <span class="app-sheet-text">
                        <span class="fw-semibold"><?= e(__((string) $item['label'])) ?></span>
                        <?php if (!empty($item['phase'])): ?>
                            <span class="small text-secondary">
                                <?= e(__('nav.soon_phase', [':phase' => (string) $item['phase']])) ?>
                            </span>
                        <?php endif; ?>
                    </span>
                    <i class="bi bi-chevron-right ms-auto" aria-hidden="true"></i>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
