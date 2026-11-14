<?php

$gasto = $gasto ?? [];
$tags = $tags ?? [];
$recurring = $recurring ?? null;
$editable = (bool) ($editable ?? false);
$gastoId = (int) ($gasto['id'] ?? 0);
$state = (string) ($gasto['estado'] ?? 'asignado');
$stateClass = [
    'asignado' => 'text-bg-success',
    'pendiente_asignacion' => 'text-bg-warning',
    'no_controlado' => 'text-bg-warning',
    'cerrado' => 'text-bg-secondary',
][$state] ?? 'text-bg-secondary';
?>
<div class="page-heading">
    <div>
        <span class="badge <?= e($stateClass) ?> mb-2"><?= e(__('expenses.states.' . $state)) ?></span>
        <h1 class="h3 mb-1"><?= money_html((int) ($gasto['monto_centavos'] ?? 0), (string) ($gasto['moneda'] ?? 'MXN')) ?></h1>
        <p class="text-secondary mb-0"><?= e(format_date($gasto['fecha'] ?? null)) ?></p>
    </div>

    <?php if (!$editable): ?>
        <span class="badge text-bg-secondary"><?= e(__('expenses.show.closed')) ?></span>
    <?php endif; ?>
</div>

<?php if ($recurring !== null && (int) ($gasto['confirmado'] ?? 1) === 0): ?>
    <div class="recurring-confirm mb-3">
        <div class="flex-grow-1">
            <strong class="d-block"><?= e(__('expenses.recurring.confirm_title')) ?></strong>
            <span class="small text-secondary"><?= e(__('expenses.recurring.confirm_body')) ?></span>
        </div>
        <form method="post" action="/gastos/confirmar" class="m-0">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $gastoId ?>">
            <button type="submit" class="btn btn-sm btn-primary"><?= e(__('expenses.recurring.correct')) ?></button>
        </form>
        <form method="post" action="/gastos/recurrente/detener" class="m-0">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $gastoId ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary"><?= e(__('expenses.recurring.stop')) ?></button>
        </form>
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="surface-card">
            <dl class="row small mb-0">
                <dt class="col-5 text-secondary fw-normal"><?= e(__('expenses.show.part')) ?></dt>
                <dd class="col-7 mb-2"><?= e((string) ($gasto['parte_metodo'] ?? '')) ?></dd>

                <dt class="col-5 text-secondary fw-normal"><?= e(__('expenses.show.subcategory')) ?></dt>
                <dd class="col-7 mb-2">
                    <?= e((string) ($gasto['subcategoria_nombre'] ?? __('expenses.show.uncategorized'))) ?>
                </dd>

                <dt class="col-5 text-secondary fw-normal"><?= e(__('expenses.show.medium')) ?></dt>
                <dd class="col-7 mb-2">
                    <?= e($gasto['medio_pago'] !== null ? __('expenses.mediums.' . (string) $gasto['medio_pago']) : '—') ?>
                </dd>

                <?php if (!empty($gasto['monto_total_compartido_centavos'])): ?>
                    <dt class="col-5 text-secondary fw-normal"><?= e(__('expenses.show.shared_total')) ?></dt>
                    <dd class="col-7 mb-2">
                        <?= money_html((int) $gasto['monto_total_compartido_centavos'], (string) $gasto['moneda']) ?>
                    </dd>
                <?php endif; ?>

                <?php if (($gasto['fuente_exceso'] ?? 'no_aplica') !== 'no_aplica'): ?>
                    <dt class="col-5 text-secondary fw-normal"><?= e(__('expenses.show.source')) ?></dt>
                    <dd class="col-7 mb-2"><?= e(__('expenses.sources.' . (string) $gasto['fuente_exceso'])) ?></dd>
                <?php endif; ?>

                <dt class="col-5 text-secondary fw-normal"><?= e(__('expenses.show.period')) ?></dt>
                <dd class="col-7 mb-2">
                    <?= e(format_date($gasto['periodo_inicio'] ?? null)) ?> –
                    <?= e(format_date($gasto['periodo_fin'] ?? null)) ?>
                </dd>

                <?php if (!empty($gasto['nota'])): ?>
                    <dt class="col-5 text-secondary fw-normal"><?= e(__('expenses.show.note')) ?></dt>
                    <dd class="col-7 mb-0"><?= nl2br(e((string) $gasto['nota'])) ?></dd>
                <?php endif; ?>
            </dl>

            <?php if (!empty($gasto['foto_path'])): ?>
                <div class="mt-3">
                    <img src="/gastos/foto?id=<?= $gastoId ?>" alt="<?= e(__('expenses.show.photo_alt')) ?>"
                         class="expense-photo">
                </div>
            <?php endif; ?>

            <?php if ($tags !== []): ?>
                <div class="mt-3 d-flex flex-wrap gap-1">
                    <?php foreach ($tags as $tag): ?>
                        <span class="tag-chip">#<?= e((string) $tag['nombre_normalizado']) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="surface-card">
            <h2 class="h5 mb-3"><?= e(__('expenses.show.actions')) ?></h2>

            <div class="d-grid gap-2">
                <?php if ($editable): ?>
                    <a href="/gastos/editar?id=<?= $gastoId ?>" class="btn btn-primary">
                        <i class="bi bi-pencil me-1" aria-hidden="true"></i><?= e(__('common.actions.edit')) ?>
                    </a>

                    <form method="post" action="/gastos/eliminar"
                          onsubmit="return confirm('<?= e(__('expenses.show.confirm_delete')) ?>')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $gastoId ?>">
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i><?= e(__('expenses.show.delete')) ?>
                        </button>
                    </form>
                <?php endif; ?>

                <form method="post" action="/ajustes/plantillas/desde-gasto">
                    <?= csrf_field() ?>
                    <input type="hidden" name="gasto_id" value="<?= $gastoId ?>">
                    <button type="submit" class="btn btn-outline-secondary w-100">
                        <i class="bi bi-star me-1" aria-hidden="true"></i><?= e(__('expenses.show.save_favorite')) ?>
                    </button>
                </form>

                <?php if ($recurring !== null && (int) ($recurring['activo'] ?? 0) === 1): ?>
                    <form method="post" action="/gastos/recurrente/detener">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $gastoId ?>">
                        <button type="submit" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-x-circle me-1" aria-hidden="true"></i><?= e(__('expenses.recurring.stop')) ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
