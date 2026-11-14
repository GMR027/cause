<?php

$errors = $errors ?? [];
$current = (string) ($state['configuracion'] ?? 'completa');
?>
<div class="onboarding-card surface-card">
    <h1 class="h4 mb-1"><?= e(__('onboarding.configuracion.heading')) ?></h1>
    <p class="text-secondary mb-4"><?= e(__('onboarding.configuracion.subtitle')) ?></p>

    <?= partial('partials/errors', ['errors' => $errors]) ?>

    <form method="post" action="/onboarding/configuracion">
        <?= csrf_field() ?>

        <div class="row g-3">
            <?php foreach (['rapida', 'completa'] as $mode): ?>
                <div class="col-12 col-md-6">
                    <label class="option-card option-card-tall h-100">
                        <input type="radio" name="configuracion" value="<?= e($mode) ?>"
                               <?= $current === $mode ? 'checked' : '' ?>>
                        <span class="option-body">
                            <span class="option-icon" aria-hidden="true">
                                <i class="bi <?= $mode === 'rapida' ? 'bi-lightning-charge' : 'bi-sliders2' ?>"></i>
                            </span>
                            <span class="option-title"><?= e(__('onboarding.configuracion.' . $mode . '.title')) ?></span>
                            <span class="option-hint"><?= e(__('onboarding.configuracion.' . $mode . '.desc')) ?></span>
                        </span>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="onboarding-actions">
            <button type="submit" class="btn btn-primary"><?= e(__('common.actions.continue')) ?></button>
        </div>
    </form>
</div>
