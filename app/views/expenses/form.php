<?php


$mode = $mode ?? 'create';
$gasto = $gasto ?? null;
$isEdit = $mode === 'edit' && $gasto !== null;
$categories = $categories ?? [];
$available = $available ?? [];
$subcategories = $subcategories ?? [];
$subcategoryUsage = $subcategoryUsage ?? [];
$tags = $tags ?? [];
$templates = $templates ?? [];
$debts = $debts ?? [];
$selectedTags = $selectedTags ?? [];
$errors = $errors ?? [];
$defaultMedium = $defaultMedium ?? null;
$currency = (string) ($currentUser['moneda_activa'] ?? 'MXN');

$partDefault = (string) ($gasto['parte_metodo'] ?? ($categories[0]['nombre'] ?? ''));
$amountValue = $gasto !== null ? number_format((int) $gasto['monto_centavos'] / 100, 2, '.', '') : '';
$sharedTotal = $gasto['monto_total_compartido_centavos'] ?? null;
$timezone = new DateTimeZone((string) ($currentUser['zona_horaria'] ?? config('app.timezone', 'UTC')));
$fecha = $gasto !== null
    ? (new DateTimeImmutable((string) $gasto['fecha'], new DateTimeZone('UTC')))->setTimezone($timezone)->format('Y-m-d')
    : (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
$periodEndLocal = (new DateTimeImmutable((string) $period['fin'], new DateTimeZone('UTC')))
    ->setTimezone($timezone)
    ->format('Y-m-d');
$selectedTagText = implode(', ', array_map(
    static fn (array $tag): string => (string) $tag['nombre_normalizado'],
    $selectedTags
));
$selectedSubcategory = $gasto['subcategoria_id'] ?? '';
$selectedMedium = (string) ($gasto['medio_pago'] ?? $defaultMedium ?? '');
$source = (string) ($gasto['fuente_exceso'] ?? 'no_aplica');
$availableJson = json_encode($available, JSON_UNESCAPED_UNICODE);
?>
<div class="flow-card surface-card">
    <span class="badge phase-badge mb-2"><?= e($isEdit ? __('expenses.form.badge_edit') : __('expenses.form.badge_create')) ?></span>
    <h1 class="h4 mb-1"><?= e($isEdit ? __('expenses.form.heading_edit') : __('expenses.form.heading_create')) ?></h1>
    <p class="text-secondary mb-3"><?= e(__('expenses.form.subtitle')) ?></p>

    <?= partial('partials/errors', ['errors' => $errors]) ?>

    <?php if ($templates !== [] && !$isEdit): ?>
        <div class="template-strip mb-4">
            <span class="small text-secondary d-block mb-2"><?= e(__('expenses.form.templates_hint')) ?></span>
            <div class="template-chips">
                <?php foreach ($templates as $template): ?>
                    <button type="button" class="template-chip" data-template
                            data-template-id="<?= (int) $template['id'] ?>"
                            data-template-name="<?= e((string) $template['nombre']) ?>"
                            data-template-amount="<?= e(number_format((int) $template['monto_centavos'] / 100, 2, '.', '')) ?>"
                            data-template-currency="<?= e((string) ($template['moneda'] ?? 'MXN')) ?>"
                            data-template-part="<?= e((string) $template['parte_metodo']) ?>"
                            data-template-subcategory="<?= (int) ($template['subcategoria_id'] ?? 0) ?>"
                            data-template-tags="<?= e(implode(',', $template['tag_names'] ?? [])) ?>">
                        <i class="bi bi-lightning-charge" aria-hidden="true"></i>
                        <span><?= e((string) $template['nombre']) ?></span>
                        <span class="template-chip-amount"><?= e(format_money((int) $template['monto_centavos'], (string) ($template['moneda'] ?? $currency))) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <form method="post"
          action="<?= $isEdit ? '/gastos/editar?id=' . (int) $gasto['id'] : '/gastos/nuevo' ?>"
          enctype="multipart/form-data"
          data-expense-form
          data-available='<?= e($availableJson) ?>'>
        <?= csrf_field() ?>
        <?php if ($isEdit): ?>
            <input type="hidden" name="id" value="<?= (int) $gasto['id'] ?>">
        <?php endif; ?>
        <input type="hidden" name="plantilla_id" value="" data-template-id>

        <div class="mb-4">
            <label class="form-label fw-semibold" for="monto"><?= e(__('expenses.form.amount_label')) ?></label>
            <div class="income-amount">
                <span class="income-currency">$</span>
                <input type="number" class="form-control" id="monto" name="monto"
                       value="<?= e($amountValue) ?>" min="0.01" step="0.01" inputmode="decimal"
                       required autofocus data-expense-amount>
                <span class="income-currency"><?= e((string) ($currentUser['moneda_activa'] ?? 'MXN')) ?></span>
            </div>
            <div class="form-text"><?= e(__('expenses.form.amount_hint')) ?></div>
        </div>

        <div class="mb-4">
            <?= partial('partials/currency-select', [
                'name' => 'moneda',
                'selected' => (string) ($_POST['moneda'] ?? ($gasto['moneda'] ?? ($currentUser['moneda_activa'] ?? 'MXN'))),
            ]) ?>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-md-7">
                <label class="form-label fw-semibold" for="parte_metodo"><?= e(__('expenses.form.part_label')) ?></label>
                <select class="form-select" id="parte_metodo" name="parte_metodo" required data-expense-part>
                    <?php foreach ($categories as $category): ?>
                        <?php $name = (string) $category['nombre']; ?>
                        <option value="<?= e($name) ?>"
                                data-tipo="<?= e((string) $category['tipo']) ?>"
                                data-asignado="<?= (int) ($available[$name]['asignado'] ?? 0) ?>"
                                data-disponible="<?= (int) ($available[$name]['disponible'] ?? 0) ?>"
                                <?= $partDefault === $name ? 'selected' : '' ?>>
                            <?= e($name) ?><?= $category['porcentaje'] !== null ? ' · ' . (int) $category['porcentaje'] . '%' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">
                    <?= e(__('expenses.form.available')) ?>:
                    <span class="fw-semibold" data-expense-available>
                        <?= e(format_money((int) ($available[$partDefault]['disponible'] ?? 0), $currency)) ?>
                    </span>
                </div>
            </div>

            <div class="col-12 col-md-5">
                <label class="form-label fw-semibold" for="fecha"><?= e(__('expenses.form.date_label')) ?></label>
                <input type="date" class="form-control" id="fecha" name="fecha"
                       value="<?= e($fecha) ?>" max="<?= e($periodEndLocal) ?>" required>
            </div>
        </div>

        <div class="excess-box mb-3" data-expense-excess hidden>
            <div class="d-flex align-items-start gap-2">
                <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
                <div>
                    <strong class="d-block"><?= e(__('expenses.form.excess_title')) ?></strong>
                    <span class="small" data-expense-excess-text></span>
                </div>
            </div>

            <fieldset class="mt-3">
                <legend class="form-label small"><?= e(__('expenses.form.source_title')) ?></legend>
                <div class="source-grid">
                    <?php foreach (['ahorro', 'inversion', 'ingreso_no_registrado', 'deuda', 'no_lo_se'] as $option): ?>
                        <label class="source-option">
                            <input type="radio" name="fuente_exceso" value="<?= e($option) ?>"
                                   <?= $source === $option ? 'checked' : '' ?>>
                            <span><?= e(__('expenses.sources.' . $option)) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </fieldset>

            <?php if (!$isEdit): ?>
                <div class="expense-debt-picker mt-3" data-expense-debt hidden>
                    <label class="form-label small" for="expense-debt-id"><?= e(__('expenses.debt_source.title')) ?></label>
                    <select class="form-select form-select-sm mb-2" id="expense-debt-id" name="deuda_id">
                        <option value="0"><?= e(__('expenses.debt_source.new')) ?></option>
                        <?php foreach ($debts as $debt): ?>
                            <option value="<?= (int) $debt['id'] ?>">
                                <?= e((string) $debt['nombre']) ?>
                                · <?= e(format_money((int) $debt['saldo_actual_centavos'], (string) $debt['moneda'])) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div data-expense-new-debt>
                        <input type="text" class="form-control form-control-sm mb-2" name="nueva_deuda_nombre"
                               maxlength="120" placeholder="<?= e(__('expenses.debt_source.name')) ?>">
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="text" inputmode="decimal" class="form-control form-control-sm"
                                       name="nueva_deuda_tasa" placeholder="<?= e(__('debts.fields.rate')) ?>">
                            </div>
                            <div class="col-6">
                                <input type="text" inputmode="decimal" class="form-control form-control-sm"
                                       name="nueva_deuda_minimo" placeholder="<?= e(__('debts.fields.minimum')) ?>">
                            </div>
                            <div class="col-12">
                                <input type="date" class="form-control form-control-sm"
                                       name="nueva_deuda_fecha_limite"
                                       aria-label="<?= e(__('debts.fields.limit')) ?>">
                            </div>
                        </div>
                    </div>
                    <div class="form-text"><?= e(__('expenses.debt_source.hint')) ?></div>
                </div>
            <?php endif; ?>
        </div>

        <details class="expense-more mb-3" <?= $isEdit ? 'open' : '' ?>>
            <summary><?= e(__('expenses.form.more')) ?></summary>

            <div class="expense-more-body">
                <div class="mb-3" data-subcategory-wrap>
                    <label class="form-label small" for="subcategoria_id"><?= e(__('expenses.form.subcategory')) ?></label>
                    <select class="form-select" id="subcategoria_id" name="subcategoria_id" data-expense-subcategory>
                        <option value=""><?= e(__('expenses.form.no_subcategory')) ?></option>
                        <?php foreach ($subcategories as $subcategory): ?>
                            <?php
                            $part = (string) ($subcategory['parte_metodo_actual'] ?? $subcategory['parte_metodo_default'] ?? '');
                            $usage = (int) ($subcategoryUsage[$part . '|' . (int) $subcategory['id']] ?? 0);
                            ?>
                            <option value="<?= (int) $subcategory['id'] ?>"
                                    data-part="<?= e($part) ?>"
                                    data-usos="<?= $usage ?>"
                                    <?= (string) $selectedSubcategory === (string) $subcategory['id'] ? 'selected' : '' ?>>
                                <?= e((string) $subcategory['nombre']) ?><?= $part !== '' ? ' · ' . e($part) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <label class="form-check small mt-2">
                        <input type="checkbox" class="form-check-input" name="recordar_parte" value="1">
                        <span class="form-check-label"><?= e(__('expenses.form.remember_part')) ?></span>
                    </label>
                </div>

                <div class="mb-3">
                    <label class="form-label small" for="nota"><?= e(__('expenses.form.note')) ?></label>
                    <textarea class="form-control" id="nota" name="nota" rows="2" maxlength="2000"><?= e((string) ($gasto['nota'] ?? '')) ?></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label small" for="tags"><?= e(__('expenses.form.tags')) ?></label>
                    <input type="text" class="form-control" id="tags" name="tags"
                           value="<?= e($selectedTagText) ?>" list="expenseTagOptions"
                           placeholder="<?= e(__('expenses.form.tags_placeholder')) ?>">
                    <datalist id="expenseTagOptions">
                        <?php foreach ($tags as $tag): ?>
                            <option value="<?= e((string) $tag['nombre_normalizado']) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <div class="form-text"><?= e(__('expenses.form.tags_hint')) ?></div>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label small" for="medio_pago"><?= e(__('expenses.form.medium')) ?></label>
                        <select class="form-select" id="medio_pago" name="medio_pago">
                            <option value=""><?= e(__('expenses.form.medium_none')) ?></option>
                            <?php foreach (['efectivo', 'debito', 'credito', 'transferencia', 'otro'] as $medium): ?>
                                <option value="<?= e($medium) ?>" <?= $selectedMedium === $medium ? 'selected' : '' ?>>
                                    <?= e(__('expenses.mediums.' . $medium)) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label small" for="foto"><?= e(__('expenses.form.photo')) ?></label>
                        <input type="file" class="form-control" id="foto" name="foto"
                               accept="image/jpeg,image/png,image/webp" data-photo-input>
                        <div class="form-text"><?= e(__('expenses.form.photo_hint')) ?></div>

                        <?php if ($isEdit && !empty($gasto['foto_path'])): ?>
                            <div class="mt-2 d-flex align-items-center gap-2">
                                <img src="/gastos/foto?id=<?= (int) $gasto['id'] ?>" alt="" class="expense-thumb">
                                <label class="form-check small mb-0">
                                    <input type="checkbox" class="form-check-input" name="eliminar_foto" value="1">
                                    <span class="form-check-label"><?= e(__('expenses.form.remove_photo')) ?></span>
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mb-2">
                    <label class="form-check">
                        <input type="checkbox" class="form-check-input" name="compartido" value="1"
                               data-shared-toggle <?= $sharedTotal !== null ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= e(__('expenses.form.shared')) ?></span>
                    </label>

                    <div class="mt-2" data-shared-fields <?= $sharedTotal !== null ? '' : 'hidden' ?>>
                        <label class="form-label small" for="monto_compartido"><?= e(__('expenses.form.shared_total')) ?></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="monto_compartido" name="monto_compartido"
                                   min="0" step="0.01" inputmode="decimal"
                                   value="<?= $sharedTotal !== null ? e(number_format((int) $sharedTotal / 100, 2, '.', '')) : '' ?>">
                        </div>
                        <div class="form-text"><?= e(__('expenses.form.shared_hint')) ?></div>
                    </div>
                </div>
            </div>
        </details>

        <div class="flow-actions">
            <a href="<?= $isEdit ? '/gastos/ver?id=' . (int) $gasto['id'] : '/inicio' ?>" class="btn btn-outline-secondary">
                <?= e(__('common.actions.cancel')) ?>
            </a>
            <button type="submit" class="btn btn-primary">
                <?= e($isEdit ? __('expenses.form.save_edit') : __('expenses.form.save_create')) ?>
            </button>
        </div>
    </form>
</div>
