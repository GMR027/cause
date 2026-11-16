<?php

$method = $method ?? null;
$period = $period ?? null;
$defaultAmount = (int) ($defaultAmount ?? 0);
$type = (string) ($type ?? 'variable');
$types = $types ?? [];
$errors = $errors ?? [];
$planId = (int) ($planId ?? 0);
$selectedCurrency = (string) ($selectedCurrency ?? ($currentUser['moneda_activa'] ?? 'MXN'));
$methodKey = $method !== null ? 'onboarding.methods.' . (string) $method['codigo'] . '.name' : '';
$methodName = $methodKey !== '' ? __($methodKey) : '';
$methodName = $methodName === $methodKey ? (string) ($method['nombre'] ?? '') : $methodName;
?>
<div class="flow-card surface-card">
    <span class="badge phase-badge mb-2"><?= e(__('income.nuevo.badge')) ?></span>
    <h1 class="h4 mb-1"><?= e(__('income.nuevo.heading')) ?></h1>
    <p class="text-secondary mb-3"><?= e(__('income.nuevo.subtitle')) ?></p>

    <?php if ($method !== null): ?>
        <div class="income-context mb-4">
            <div>
                <span class="small text-secondary d-block"><?= e(__('income.nuevo.method')) ?></span>
                <span class="fw-semibold"><?= e($methodName) ?></span>
            </div>
            <div class="text-end">
                <span class="small text-secondary d-block"><?= e(__('income.nuevo.period')) ?></span>
                <span class="fw-semibold">
                    <?= e(format_date($period['inicio'] ?? null)) ?> – <?= e(format_date($period['fin'] ?? null)) ?>
                </span>
            </div>
        </div>
    <?php endif; ?>

    <?= partial('partials/errors', ['errors' => $errors]) ?>

    <form method="post" action="/ingresos/nuevo" data-income-form>
        <?= csrf_field() ?>
        <?php if ($planId > 0): ?>
            <input type="hidden" name="plan_id" value="<?= $planId ?>">
            <div class="alert alert-info py-2">
                <i class="bi bi-clipboard-check me-1" aria-hidden="true"></i>
                <?= e(__('planner.show.register_income')) ?>
            </div>
        <?php endif; ?>

        <div class="mb-4">
            <label class="form-label fw-semibold" for="monto"><?= e(__('income.nuevo.amount_label')) ?></label>
            <div class="income-amount">
                <span class="income-currency">$</span>
                <input type="number" class="form-control" id="monto" name="monto"
                       value="<?= e(number_format($defaultAmount / 100, 2, '.', '')) ?>"
                       min="0.01" step="0.01" inputmode="decimal" required autofocus>
                <span class="income-currency"><?= e($selectedCurrency) ?></span>
            </div>
            <div class="form-text"><?= e(__('income.nuevo.amount_hint')) ?></div>
        </div>

        <div class="mb-4">
            <?= partial('partials/currency-select', [
                'name' => 'moneda',
                'selected' => $selectedCurrency,
            ]) ?>
        </div>

        <fieldset class="mb-4">
            <legend class="form-label fw-semibold"><?= e(__('income.nuevo.type')) ?></legend>

            <div class="option-grid option-grid-3">
                <?php foreach ($types as $option): ?>
                    <label class="option-card">
                        <input type="radio" name="tipo" value="<?= e((string) $option) ?>"
                               <?= $type === $option ? 'checked' : '' ?> data-income-type>
                        <span class="option-body">
                            <span class="option-title"><?= e(__('income.types.' . (string) $option)) ?></span>
                            <span class="option-hint"><?= e(__('income.types.' . (string) $option . '_hint')) ?></span>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="income-extra-note" data-extra-note <?= $type === 'extra' ? '' : 'hidden' ?>>
            <i class="bi bi-stars me-1" aria-hidden="true"></i>
            <?= e(__('income.nuevo.extra_note')) ?>
        </div>

        <p class="small text-secondary mb-0">
            <?= e(__('income.nuevo.next_hint')) ?>
        </p>

        <div class="flow-actions">
            <a href="/inicio" class="btn btn-outline-secondary"><?= e(__('common.actions.cancel')) ?></a>
            <button type="submit" class="btn btn-primary"><?= e(__('common.actions.continue')) ?></button>
        </div>
    </form>
</div>
