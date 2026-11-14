<?php

$types = $types ?? [];
$errors = $errors ?? [];
$current = (string) ($state['tipo_ingreso'] ?? 'fijo');
?>
<div class="onboarding-card surface-card">
    <h1 class="h4 mb-1"><?= e(__('onboarding.tipo_ingreso.heading')) ?></h1>
    <p class="text-secondary mb-4"><?= e(__('onboarding.tipo_ingreso.subtitle')) ?></p>

    <?= partial('partials/errors', ['errors' => $errors]) ?>

    <form method="post" action="/onboarding/tipo-ingreso">
        <?= csrf_field() ?>

        <div class="option-grid option-grid-3">
            <?php foreach ($types as $type): ?>
                <label class="option-card">
                    <input type="radio" name="tipo_ingreso" value="<?= e((string) $type) ?>"
                           <?= $current === $type ? 'checked' : '' ?>>
                    <span class="option-body">
                        <span class="option-title"><?= e(__('onboarding.tipo_ingreso.' . (string) $type)) ?></span>
                        <span class="option-hint"><?= e(__('onboarding.tipo_ingreso.' . (string) $type . '_hint')) ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="onboarding-actions">
            <button type="submit" class="btn btn-primary"><?= e(__('common.actions.continue')) ?></button>
        </div>
    </form>
</div>
