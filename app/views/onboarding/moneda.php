<?php

$currencies = $currencies ?? [];
$errors = $errors ?? [];
$current = (string) ($state['moneda'] ?? 'MXN');
?>
<div class="onboarding-card surface-card">
    <h1 class="h4 mb-1"><?= e(__('onboarding.moneda.heading')) ?></h1>
    <p class="text-secondary mb-4"><?= e(__('onboarding.moneda.subtitle')) ?></p>

    <?= partial('partials/errors', ['errors' => $errors]) ?>

    <form method="post" action="/onboarding/moneda">
        <?= csrf_field() ?>

        <div class="option-grid">
            <?php foreach ($currencies as $currency): ?>
                <label class="option-card">
                    <input type="radio" name="moneda" value="<?= e((string) $currency) ?>"
                           <?= $current === $currency ? 'checked' : '' ?>>
                    <span class="option-body">
                        <span class="option-title"><?= e((string) $currency) ?></span>
                        <span class="option-hint"><?= e(__('onboarding.moneda.' . strtolower((string) $currency))) ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="onboarding-actions">
            <button type="submit" class="btn btn-primary"><?= e(__('common.actions.continue')) ?></button>
        </div>
    </form>
</div>
