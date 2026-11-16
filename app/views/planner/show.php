<?php

$plan = $plan ?? [];
$snapshot = $snapshot ?? [];
$status = (string) ($status ?? 'activo');
$editable = (bool) ($editable ?? false);
$methodChanged = (bool) ($methodChanged ?? false);
$currency = (string) ($currentUser['moneda_activa'] ?? 'MXN');
$planCurrency = (string) ($plan['moneda'] ?? $currency);
$summary = is_array($snapshot['summary'] ?? null) ? $snapshot['summary'] : [];
$warnings = is_array($snapshot['warnings'] ?? null) ? $snapshot['warnings'] : [];
$commitments = is_array($snapshot['commitments'] ?? null) ? $snapshot['commitments'] : [];
$sections = is_array($snapshot['sections'] ?? null) ? $snapshot['sections'] : [];
$debts = is_array($snapshot['debts'] ?? null) ? $snapshot['debts'] : [];
$statusClass = match ($status) {
    'completado' => 'text-bg-success',
    'vencido' => 'text-bg-secondary',
    default => 'text-bg-primary',
};
$fechaLarga = !empty($plan['fecha_ingreso']) ? format_date((string) $plan['fecha_ingreso'] . ' 12:00:00') : '—';
$amountRaw = number_format((int) ($plan['monto_centavos'] ?? 0) / 100, 2, '.', '');
$incomeUrl = '/ingresos/nuevo?monto=' . rawurlencode($amountRaw)
    . '&moneda=' . rawurlencode($planCurrency)
    . '&plan_id=' . (int) ($plan['id'] ?? 0);
$methodName = (string) ($plan['metodo_nombre'] ?? ($snapshot['metodo_nombre'] ?? ''));
?>
<div class="page-heading">
    <div>
        <span class="badge planner-status <?= e($statusClass) ?> mb-2">
            <?= e(__('planner.status.' . $status)) ?>
        </span>
        <h1 class="h3 mb-1">
            <?= e(__('planner.show.heading', [':date' => $fechaLarga])) ?>
        </h1>
        <p class="text-secondary mb-0">
            <?= e(__('planner.show.subtitle', [':created' => format_datetime($plan['created_at'] ?? null)])) ?>
        </p>
    </div>
    <a class="btn btn-outline-secondary" href="/planeador">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
        <?= e(__('planner.show.back')) ?>
    </a>
</div>

<?php if ($status === 'completado'): ?>
    <div class="alert alert-success"><?= e(__('planner.show.completed_notice')) ?></div>
<?php elseif ($status === 'vencido'): ?>
    <div class="alert alert-secondary"><?= e(__('planner.show.past_notice')) ?></div>
<?php endif; ?>

<?php if ($methodChanged && $status !== 'completado'): ?>
    <div class="alert alert-warning"><?= e(__('planner.show.method_changed')) ?></div>
<?php endif; ?>

<div class="surface-card mb-3">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-1">
        <div>
            <span class="small text-secondary d-block"><?= e(__('planner.summary.income')) ?></span>
            <span class="h5 mb-0"><?= money_html((int) ($plan['monto_centavos'] ?? 0), $planCurrency) ?></span>
            <?php if ($planCurrency !== $currency): ?>
                <span class="small text-secondary d-block">
                    ≈ <?= money_html((int) ($plan['monto_principal_centavos'] ?? 0), $currency) ?>
                </span>
            <?php endif; ?>
        </div>
        <?php if ($methodName !== ''): ?>
            <div class="text-end">
                <span class="small text-secondary d-block"><?= e(__('income.nuevo.method')) ?></span>
                <span class="fw-semibold"><?= e($methodName) ?></span>
            </div>
        <?php endif; ?>
    </div>
    <p class="small text-secondary mb-0"><?= e(__('planner.show.snapshot_notice')) ?></p>
</div>

<?= partial('planner/partials/warnings', ['warnings' => $warnings]) ?>
<?= partial('planner/partials/summary', [
    'summary' => $summary,
    'currency' => $currency,
    'days' => (int) ($snapshot['dias_restantes'] ?? 0),
]) ?>

<section class="surface-card mb-3">
    <h2 class="h6 mb-1"><?= e(__('planner.commitments.title')) ?></h2>
    <p class="small text-secondary mb-3"><?= e(__('planner.commitments.subtitle')) ?></p>
    <?= partial('planner/partials/commitments', [
        'commitments' => $commitments,
        'currency' => $currency,
    ]) ?>
</section>

<section class="surface-card mb-3">
    <h2 class="h6 mb-1"><?= e(__('planner.sections.title')) ?></h2>
    <p class="small text-secondary mb-3"><?= e(__('planner.sections.subtitle')) ?></p>
    <?= partial('planner/partials/sections', [
        'sections' => $sections,
        'currency' => $currency,
    ]) ?>
</section>

<?php if ($debts !== []): ?>
    <section class="surface-card mb-3">
        <h2 class="h6 mb-1"><?= e(__('planner.debts.title')) ?></h2>
        <p class="small text-secondary mb-3"><?= e(__('planner.debts.subtitle')) ?></p>
        <ul class="planner-items">
            <?php foreach ($debts as $debt): ?>
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

<div class="d-flex flex-wrap gap-2">
    <?php if ($editable): ?>
        <a class="btn btn-primary" href="<?= e($incomeUrl) ?>">
            <i class="bi bi-cash-coin me-1" aria-hidden="true"></i>
            <?= e(__('planner.show.register_income')) ?>
        </a>
        <a class="btn btn-outline-primary" href="/planeador/editar?id=<?= (int) $plan['id'] ?>">
            <i class="bi bi-pencil me-1" aria-hidden="true"></i>
            <?= e(__('planner.show.edit')) ?>
        </a>
        <form method="post" action="/planeador/recalcular" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
            <button class="btn btn-outline-secondary" type="submit">
                <i class="bi bi-arrow-repeat me-1" aria-hidden="true"></i>
                <?= e(__('planner.show.recalculate')) ?>
            </button>
        </form>
        <form method="post" action="/planeador/completar" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
            <button class="btn btn-outline-success" type="submit">
                <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>
                <?= e(__('planner.show.complete')) ?>
            </button>
        </form>
    <?php endif; ?>

    <a class="btn btn-outline-secondary" href="/planeador/nuevo?duplicar=<?= (int) $plan['id'] ?>">
        <i class="bi bi-copy me-1" aria-hidden="true"></i>
        <?= e(__('planner.show.duplicate')) ?>
    </a>

    <form method="post" action="/planeador/eliminar" class="d-inline ms-auto"
          data-confirm="<?= e(__('planner.show.delete_confirm')) ?>" data-confirm-danger>
        <?= csrf_field() ?>
        <input type="hidden" name="plan_id" value="<?= (int) $plan['id'] ?>">
        <button class="btn btn-outline-danger" type="submit">
            <i class="bi bi-trash me-1" aria-hidden="true"></i>
            <?= e(__('planner.show.delete')) ?>
        </button>
    </form>
</div>
