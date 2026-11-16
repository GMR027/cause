<?php

$plan = $plan ?? [];
$planId = (int) ($planId ?? 0);
$items = is_array($plan['items'] ?? null) ? $plan['items'] : [];
$currency = (string) ($plan['principal'] ?? ($currentUser['moneda_activa'] ?? 'MXN'));
$isEdit = $planId > 0;
$saveAction = $isEdit ? '/planeador/actualizar' : '/planeador/guardar';
$backUrl = $isEdit ? '/planeador/editar?id=' . $planId : '/planeador/nuevo';
$moneyValue = static fn (int $cents): string => number_format($cents / 100, 2, '.', '');
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('planner.preview.title')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('planner.preview.heading')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('planner.preview.subtitle')) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e($backUrl) ?>">
        <i class="bi bi-pencil me-1" aria-hidden="true"></i>
        <?= e(__('planner.preview.back')) ?>
    </a>
</div>

<?= partial('planner/partials/warnings', ['warnings' => $plan['warnings'] ?? []]) ?>
<?= partial('planner/partials/summary', [
    'summary' => $plan['summary'] ?? [],
    'currency' => $currency,
    'days' => (int) ($plan['dias_restantes'] ?? 0),
]) ?>

<section class="surface-card mb-3">
    <h2 class="h6 mb-1"><?= e(__('planner.commitments.title')) ?></h2>
    <p class="small text-secondary mb-3"><?= e(__('planner.commitments.subtitle')) ?></p>
    <?= partial('planner/partials/commitments', [
        'commitments' => $plan['commitments'] ?? [],
        'currency' => $currency,
    ]) ?>
</section>

<section class="surface-card mb-3">
    <h2 class="h6 mb-1"><?= e(__('planner.sections.title')) ?></h2>
    <p class="small text-secondary mb-3"><?= e(__('planner.sections.subtitle')) ?></p>
    <?= partial('planner/partials/sections', [
        'sections' => $plan['sections'] ?? [],
        'currency' => $currency,
    ]) ?>
</section>

<?php if (($plan['debts'] ?? []) !== []): ?>
    <section class="surface-card mb-3">
        <h2 class="h6 mb-1"><?= e(__('planner.debts.title')) ?></h2>
        <p class="small text-secondary mb-3"><?= e(__('planner.debts.subtitle')) ?></p>
        <ul class="planner-items">
            <?php foreach ($plan['debts'] as $debt): ?>
                <li class="planner-item">
                    <span>
                        <?= e((string) $debt['nombre']) ?>
                        <?php if (!empty($debt['fecha_limite'])): ?>
                            <span class="small text-secondary d-block">
                                <?= e(__('planner.debts.limit')) ?>: <?= e(format_date($debt['fecha_limite'] . ' 12:00:00')) ?>
                            </span>
                        <?php endif; ?>
                    </span>
                    <span class="planner-item-amount"><?= money_html((int) $debt['saldo_centavos'], $currency) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php endif; ?>

<form method="post" action="<?= e($saveAction) ?>" class="d-flex flex-wrap justify-content-end gap-2">
    <?= csrf_field() ?>
    <input type="hidden" name="items_present" value="1">
    <input type="hidden" name="fecha_ingreso" value="<?= e((string) ($plan['fecha_ingreso'] ?? '')) ?>">
    <input type="hidden" name="monto" value="<?= e($moneyValue((int) ($plan['monto_centavos'] ?? 0))) ?>">
    <input type="hidden" name="moneda" value="<?= e((string) ($plan['moneda'] ?? $currency)) ?>">
    <input type="hidden" name="notas" value="<?= e((string) ($plan['notas'] ?? '')) ?>">
    <?php if ($isEdit): ?>
        <input type="hidden" name="plan_id" value="<?= $planId ?>">
    <?php endif; ?>
    <?php foreach ($items as $index => $item): ?>
        <input type="hidden" name="items[<?= (int) $index ?>][seccion]" value="<?= e((string) $item['seccion']) ?>">
        <input type="hidden" name="items[<?= (int) $index ?>][concepto]" value="<?= e((string) $item['concepto']) ?>">
        <input type="hidden" name="items[<?= (int) $index ?>][monto]" value="<?= e($moneyValue((int) $item['monto_centavos'])) ?>">
    <?php endforeach; ?>

    <a class="btn btn-outline-secondary" href="<?= e($backUrl) ?>"><?= e(__('planner.preview.back')) ?></a>
    <button class="btn btn-primary" type="submit">
        <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>
        <?= e(__('planner.preview.save')) ?>
    </button>
</form>
