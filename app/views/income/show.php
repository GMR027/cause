<?php

$ingreso = $ingreso ?? [];
$asignaciones = $asignaciones ?? [];
$editable = (bool) ($editable ?? false);
$errors = $errors ?? [];
$confirm = $confirm ?? null;
$movements = $movements ?? [];
$onlyIncome = (bool) ($onlyIncome ?? true);
$movementTotal = array_sum($movements);
$currency = (string) ($ingreso['moneda'] ?? 'MXN');
$id = (int) ($ingreso['id'] ?? 0);
$amountValue = $confirm !== null && isset($confirm['monto'])
    ? (string) $confirm['monto']
    : number_format((int) ($ingreso['monto_centavos'] ?? 0) / 100, 2, '.', '');
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('income.show.title')) ?></span>
        <h1 class="h3 mb-1"><?= money_html((int) ($ingreso['monto_centavos'] ?? 0), $currency) ?></h1>
        <p class="text-secondary mb-0">
            <?= e(__('income.types.' . (string) ($ingreso['tipo'] ?? 'fijo'))) ?>
            · <?= e(format_date($ingreso['fecha'] ?? null)) ?>
            · <?= e(format_date($ingreso['periodo_inicio'] ?? null)) ?> – <?= e(format_date($ingreso['periodo_fin'] ?? null)) ?>
        </p>
    </div>
    <a href="/movimientos" class="btn btn-outline-secondary"><?= e(__('income.show.back')) ?></a>
</div>

<?= partial('partials/errors', ['errors' => $errors]) ?>

<?php if ($confirm !== null): ?>
    <div class="alert alert-warning" role="alert">
        <strong class="d-block mb-1"><?= e(__('income.show.confirm_title')) ?></strong>
        <p class="mb-2"><?= e(__($confirm['action'] === 'eliminar' ? 'income.show.confirm_delete' : 'income.show.confirm_edit')) ?></p>
        <form method="post" action="/ingresos/<?= $confirm['action'] === 'eliminar' ? 'eliminar' : 'editar' ?>" class="d-flex flex-wrap gap-2">
            <?= csrf_field() ?>
            <input type="hidden" name="ingreso_id" value="<?= $id ?>">
            <input type="hidden" name="confirmar" value="1">
            <?php if ($confirm['action'] !== 'eliminar'): ?>
                <input type="hidden" name="monto" value="<?= e($amountValue) ?>">
            <?php endif; ?>
            <button type="submit" class="btn btn-warning btn-sm"><?= e(__('income.show.confirm_yes')) ?></button>
            <a href="/ingresos/ver?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm"><?= e(__('common.actions.cancel')) ?></a>
        </form>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-lg-6">
        <div class="surface-card h-100">
            <h2 class="h5 mb-3"><?= e(__('income.show.split')) ?></h2>
            <ul class="method-list mb-0">
                <?php foreach ($asignaciones as $row): ?>
                    <li>
                        <span><?= e((string) $row['categoria']) ?></span>
                        <span class="d-inline-flex align-items-baseline gap-2">
                            <span class="fw-semibold"><?= money_html((int) $row['monto_centavos'], (string) $row['moneda']) ?></span>
                            <span class="small text-secondary"><?= e((string) $row['estado'] === 'pendiente_asignacion'
                                ? __('expenses.states.pendiente_asignacion')
                                : __('income.states.' . (string) $row['estado'])) ?></span>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="surface-card h-100">
            <?php if (!$editable): ?>
                <p class="text-secondary mb-0"><?= e(__('income.show.closed')) ?></p>
            <?php else: ?>
                <h2 class="h5 mb-1"><?= e(__('income.show.edit_title')) ?></h2>
                <p class="small text-secondary mb-3"><?= e(__('income.show.edit_hint')) ?></p>
                <form method="post" action="/ingresos/editar" class="mb-4">
                    <?= csrf_field() ?>
                    <input type="hidden" name="ingreso_id" value="<?= $id ?>">
                    <label class="form-label" for="income_amount"><?= e(__('finance.amount')) ?></label>
                    <div class="input-group mb-3">
                        <span class="input-group-text"><?= e($currency) ?></span>
                        <input type="number" class="form-control" id="income_amount" name="monto"
                               min="0.01" step="0.01" inputmode="decimal" value="<?= e($amountValue) ?>" required>
                    </div>
                    <button type="submit" class="btn btn-primary"><?= e(__('income.show.save')) ?></button>
                </form>

                <form method="post" action="/ingresos/eliminar"
                      data-confirm="<?= e(__('income.show.delete_confirm')) ?>" data-confirm-danger>
                    <?= csrf_field() ?>
                    <input type="hidden" name="ingreso_id" value="<?= $id ?>">
                    <?php if ($movementTotal > 0): ?>
                        <div class="delete-movements mb-3">
                            <label class="form-check mb-1">
                                <input type="checkbox" class="form-check-input" name="borrar_movimientos" value="1"
                                       <?= $onlyIncome ? 'checked' : '' ?>>
                                <span class="form-check-label fw-semibold">
                                    <?= e(__('income.show.delete_movements', [':count' => (string) $movementTotal])) ?>
                                </span>
                            </label>
                            <p class="small text-secondary mb-1">
                                <?= e(implode(' · ', array_map(
                                    static fn (string $kind, int $count): string => __('income.show.movement_kinds.' . $kind, [':count' => (string) $count]),
                                    array_keys($movements),
                                    array_values($movements)
                                ))) ?>
                            </p>
                            <p class="small text-secondary mb-0">
                                <?= e(__($onlyIncome ? 'income.show.delete_movements_hint' : 'income.show.delete_movements_shared')) ?>
                            </p>
                        </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-outline-danger">
                        <i class="bi bi-trash me-1" aria-hidden="true"></i><?= e(__('income.show.delete')) ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>
