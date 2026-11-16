<?php

$summary = $summary ?? [];
$currency = (string) ($currency ?? ($currentUser['moneda_activa'] ?? 'MXN'));
$days = (int) ($days ?? 0);
$summary = array_merge([
    'ingreso_centavos' => 0,
    'obligaciones_centavos' => 0,
    'apartados_centavos' => 0,
    'disponible_gastar_centavos' => 0,
    'despues_obligaciones_centavos' => 0,
    'deficit_centavos' => 0,
], $summary);
$spendable = (int) $summary['disponible_gastar_centavos'];
?>
<div class="planner-summary mb-3">
    <div class="finance-card">
        <span class="finance-card-label"><?= e(__('planner.summary.income')) ?></span>
        <span class="finance-card-value"><?= money_html((int) $summary['ingreso_centavos'], $currency) ?></span>
        <span class="small text-secondary"><?= e(__('planner.summary.income_hint')) ?></span>
    </div>
    <div class="finance-card">
        <span class="finance-card-label"><?= e(__('planner.summary.obligations')) ?></span>
        <span class="finance-card-value"><?= money_html((int) $summary['obligaciones_centavos'], $currency) ?></span>
        <span class="small text-secondary"><?= e(__('planner.summary.obligations_hint')) ?></span>
    </div>
    <div class="finance-card">
        <span class="finance-card-label"><?= e(__('planner.summary.reserved')) ?></span>
        <span class="finance-card-value"><?= money_html((int) $summary['apartados_centavos'], $currency) ?></span>
        <span class="small text-secondary"><?= e(__('planner.summary.reserved_hint')) ?></span>
    </div>
    <div class="finance-card<?= $spendable < 0 ? ' border-danger' : ' finance-card-accent' ?>">
        <span class="finance-card-label"><?= e(__('planner.summary.spendable')) ?></span>
        <span class="finance-card-value<?= $spendable < 0 ? ' text-danger' : '' ?>">
            <?= money_html($spendable, $currency) ?>
        </span>
        <span class="small text-secondary"><?= e(__('planner.summary.spendable_hint')) ?></span>
    </div>
    <div class="finance-card">
        <span class="finance-card-label"><?= e(__('planner.summary.after')) ?></span>
        <span class="finance-card-value"><?= money_html((int) $summary['despues_obligaciones_centavos'], $currency) ?></span>
        <span class="small text-secondary"><?= e(__('planner.summary.after_hint')) ?></span>
    </div>
    <div class="finance-card">
        <span class="finance-card-label"><?= e(__('planner.summary.days')) ?></span>
        <span class="finance-card-value"><?= $days <= 0 ? e(__('planner.summary.today')) : e(__('planner.summary.days_value', [':count' => (string) $days])) ?></span>
    </div>
</div>

<?php if ((int) $summary['deficit_centavos'] > 0): ?>
    <div class="alert alert-danger d-flex flex-wrap align-items-center gap-2">
        <i class="bi bi-exclamation-octagon" aria-hidden="true"></i>
        <span>
            <?= e(__('planner.summary.deficit')) ?>:
            <strong><?= money_html((int) $summary['deficit_centavos'], $currency) ?></strong>
        </span>
        <span class="small"><?= e(__('planner.summary.deficit_hint')) ?></span>
    </div>
<?php endif; ?>
