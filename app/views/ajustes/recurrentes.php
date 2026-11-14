<?php


$recurrings = $recurrings ?? [];
$editing = $editing ?? null;
$categories = $categories ?? [];
$subcategories = $subcategories ?? [];
$timezone = new DateTimeZone((string) ($currentUser['zona_horaria'] ?? config('app.timezone', 'UTC')));
$isEditing = $editing !== null;
$nextDate = '';

if ($editing !== null) {
    $nextDate = (new DateTimeImmutable((string) $editing['proxima_ejecucion'], new DateTimeZone('UTC')))
        ->setTimezone($timezone)
        ->format('Y-m-d');
} else {
    $nextDate = date('Y-m-d');
}
?>
<div class="page-heading">
    <div>
        <h1 class="h3 mb-1"><?= e(__('expenses.recurring.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('expenses.recurring.subtitle')) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="/ajustes/notificaciones/calendario.ics" class="btn btn-outline-secondary">
            <i class="bi bi-download me-1" aria-hidden="true"></i>
            <?= e(__('calendar.ics.export')) ?>
        </a>
        <a href="/ajustes" class="btn btn-outline-secondary d-none d-xl-inline-flex"><?= e(__('common.actions.back')) ?></a>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-5">
        <div class="surface-card">
            <h2 class="h5 mb-1">
                <?= e($isEditing ? __('expenses.recurring.edit_title') : __('expenses.recurring.create_title')) ?>
            </h2>
            <p class="small text-secondary mb-3"><?= e(__('expenses.recurring.create_hint')) ?></p>

            <form method="post" action="<?= $isEditing ? '/ajustes/recurrentes/actualizar' : '/ajustes/recurrentes' ?>">
                <?= csrf_field() ?>
                <?php if ($isEditing): ?>
                    <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label small" for="nombre"><?= e(__('expenses.recurring.name')) ?></label>
                    <input type="text" class="form-control" id="nombre" name="nombre" maxlength="120"
                           value="<?= e((string) ($editing['nombre'] ?? '')) ?>" required>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-8">
                        <label class="form-label small" for="monto"><?= e(__('expenses.recurring.amount')) ?></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="monto" name="monto"
                                   min="0.01" step="0.01" inputmode="decimal"
                                   value="<?= $editing !== null ? e(number_format((int) $editing['monto_centavos'] / 100, 2, '.', '')) : '' ?>"
                                   required>
                        </div>
                    </div>
                    <div class="col-4 d-flex align-items-end">
                        <label class="form-check small mb-2">
                            <input type="checkbox" class="form-check-input" name="monto_variable" value="1"
                                   <?= !empty($editing['monto_variable']) ? 'checked' : '' ?>>
                            <span class="form-check-label"><?= e(__('expenses.recurring.variable')) ?></span>
                        </label>
                    </div>
                </div>

                <div class="mb-3">
                    <?= partial('partials/currency-select', [
                        'name' => 'moneda',
                        'selected' => (string) ($editing['moneda'] ?? ($currentUser['moneda_activa'] ?? 'MXN')),
                    ]) ?>
                </div>

                <div class="mb-3">
                    <label class="form-label small" for="parte_metodo"><?= e(__('expenses.recurring.part')) ?></label>
                    <select class="form-select" id="parte_metodo" name="parte_metodo" required>
                        <?php foreach ($categories as $category): ?>
                            <?php $name = (string) $category['nombre']; ?>
                            <option value="<?= e($name) ?>" <?= ($editing['parte_metodo'] ?? '') === $name ? 'selected' : '' ?>>
                                <?= e($name) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label small" for="subcategoria_id"><?= e(__('expenses.recurring.subcategory')) ?></label>
                    <select class="form-select" id="subcategoria_id" name="subcategoria_id">
                        <option value=""><?= e(__('expenses.form.no_subcategory')) ?></option>
                        <?php foreach ($subcategories as $subcategory): ?>
                            <option value="<?= (int) $subcategory['id'] ?>"
                                    <?= (string) ($editing['subcategoria_id'] ?? '') === (string) $subcategory['id'] ? 'selected' : '' ?>>
                                <?= e((string) $subcategory['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small" for="recurrencia"><?= e(__('expenses.recurring.recurrence')) ?></label>
                        <select class="form-select" id="recurrencia" name="recurrencia">
                            <?php foreach (['diaria', 'semanal', 'mensual', 'anual'] as $recurrence): ?>
                                <option value="<?= e($recurrence) ?>"
                                        <?= ($editing['recurrencia'] ?? 'mensual') === $recurrence ? 'selected' : '' ?>>
                                    <?= e(__('onboarding.recurrencia.' . $recurrence)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small" for="proxima_ejecucion"><?= e(__('expenses.recurring.next')) ?></label>
                        <input type="date" class="form-control" id="proxima_ejecucion" name="proxima_ejecucion"
                               value="<?= e($nextDate) ?>" min="<?= e((new DateTimeImmutable('now', $timezone))->format('Y-m-d')) ?>" required>
                    </div>
                </div>

                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <?= e($isEditing ? __('common.actions.save') : __('expenses.recurring.create')) ?>
                    </button>
                    <?php if ($isEditing): ?>
                        <a href="/ajustes/recurrentes" class="btn btn-outline-secondary"><?= e(__('common.actions.cancel')) ?></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="surface-card">
            <h2 class="h5 mb-3"><?= e(__('expenses.recurring.list_title')) ?></h2>

            <?php if ($recurrings === []): ?>
                <p class="text-secondary mb-0"><?= e(__('expenses.recurring.empty')) ?></p>
            <?php else: ?>
                <div class="settings-list">
                    <?php foreach ($recurrings as $recurring): ?>
                        <div class="settings-row">
                            <div class="flex-grow-1">
                                <span class="fw-semibold d-block">
                                    <?= e((string) $recurring['nombre']) ?>
                                    <?php if (!empty($recurring['deuda_id'])): ?>
                                        <span class="badge text-bg-info ms-1"><?= e(__('debts.reminder.badge')) ?></span>
                                    <?php endif; ?>
                                    <?php if ((int) $recurring['activo'] !== 1): ?>
                                        <span class="badge text-bg-secondary ms-1"><?= e(__('expenses.recurring.inactive')) ?></span>
                                    <?php endif; ?>
                                </span>
                                <span class="small text-secondary">
                                    <?= e((string) $recurring['parte_metodo']) ?>
                                    <?php if (!empty($recurring['subcategoria'])): ?>
                                        · <?= e((string) $recurring['subcategoria']) ?>
                                    <?php endif; ?>
                                    · <?= e(__('onboarding.recurrencia.' . (string) $recurring['recurrencia'])) ?>
                                    · <?= e(__('expenses.recurring.next_short')) ?>:
                                    <?= e(format_date($recurring['proxima_ejecucion'] ?? null)) ?>
                                </span>
                            </div>

                            <span class="fw-semibold text-nowrap">
                                <?= money_html((int) $recurring['monto_centavos'], (string) $recurring['moneda']) ?>
                            </span>

                            <a href="/ajustes/recurrentes?editar=<?= (int) $recurring['id'] ?>"
                               class="btn btn-sm btn-outline-secondary" aria-label="<?= e(__('common.actions.edit')) ?>">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </a>

                            <?php if ((int) $recurring['activo'] === 1): ?>
                                <form method="post" action="/ajustes/recurrentes/desactivar" class="m-0"
                                      onsubmit="return confirm('<?= e(__('expenses.recurring.confirm_deactivate')) ?>')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $recurring['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <?= e(__('expenses.recurring.deactivate')) ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
