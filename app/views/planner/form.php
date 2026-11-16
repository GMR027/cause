<?php

$plan = $plan ?? [];
$mode = (string) ($mode ?? 'create');
$planId = (int) ($planId ?? 0);
$itemsPresent = (bool) ($itemsPresent ?? false);
$duplicatedFrom = $duplicatedFrom ?? null;
$currencies = $currencies ?? [];
$currency = (string) ($plan['moneda'] ?? ($currentUser['moneda_activa'] ?? 'MXN'));
$today = (string) ($today ?? gmdate('Y-m-d'));
$isEdit = $mode === 'edit' && $planId > 0;
$action = '/planeador/calcular';
$saveAction = $isEdit ? '/planeador/actualizar' : '/planeador/guardar';
$errors = array_map(static fn (string $error): string => __($error), (array) ($plan['errors'] ?? []));
$sections = is_array($plan['sections'] ?? null) ? $plan['sections'] : [];
$items = is_array($plan['items'] ?? null) ? $plan['items'] : [];
$itemsBySection = [];

foreach ($items as $item) {
    $itemsBySection[(string) $item['seccion']][] = $item;
}

$index = 0;
$pct = static fn (float $value): string => rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
$moneyValue = static fn (int $cents): string => number_format($cents / 100, 2, '.', ',');
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('planner.form.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__($isEdit ? 'planner.form.title_edit' : 'planner.form.title_new')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('planner.form.subtitle')) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="/planeador">
        <i class="bi bi-arrow-left me-1" aria-hidden="true"></i>
        <?= e(__('planner.show.back')) ?>
    </a>
</div>

<?php if ($duplicatedFrom !== null): ?>
    <div class="alert alert-info"><?= e(__('planner.form.duplicated_notice')) ?></div>
<?php endif; ?>

<?= partial('partials/errors', ['errors' => $errors]) ?>

<?php if ($plan['errors'] !== [] && in_array('planner.errors.no_method', (array) $plan['errors'], true)): ?>
    <div class="surface-card text-center py-5">
        <i class="bi bi-sliders fs-2 text-secondary" aria-hidden="true"></i>
        <p class="mt-2 mb-1 fw-semibold"><?= e(__('planner.index.no_method_title')) ?></p>
        <p class="small text-secondary mb-3"><?= e(__('planner.index.no_method_text')) ?></p>
        <a class="btn btn-primary" href="/ajustes/ingreso"><?= e(__('planner.index.no_method_action')) ?></a>
    </div>
<?php else: ?>
    <form method="post" action="<?= e($action) ?>" class="surface-card mb-4" data-planner-form>
        <?= csrf_field() ?>
        <input type="hidden" name="items_present" value="1">
        <?php if ($isEdit): ?>
            <input type="hidden" name="plan_id" value="<?= $planId ?>">
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-12 col-md-4">
                <label class="form-label" for="planner-date"><?= e(__('planner.form.date_label')) ?></label>
                <input class="form-control" type="date" id="planner-date" name="fecha_ingreso"
                       min="<?= e($today) ?>" value="<?= e((string) ($plan['fecha_ingreso'] ?? $today)) ?>" required>
                <div class="form-text"><?= e(__('planner.form.date_hint')) ?></div>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="planner-amount"><?= e(__('planner.form.amount_label')) ?></label>
                <input class="form-control text-end" type="text" inputmode="decimal" id="planner-amount" name="monto"
                       value="<?= e($plan['monto_centavos'] > 0 ? $moneyValue((int) $plan['monto_centavos']) : '') ?>"
                       placeholder="0.00" data-money-input autocomplete="off" required>
                <div class="form-text"><?= e(__('planner.form.amount_hint')) ?></div>
            </div>
            <div class="col-12 col-md-4">
                <?= partial('partials/currency-select', [
                    'name' => 'moneda',
                    'selected' => $currency,
                    'currencies' => $currencies,
                    'label' => __('planner.form.currency_label'),
                    'id' => 'planner-currency',
                ]) ?>
            </div>
            <div class="col-12">
                <label class="form-label" for="planner-notes"><?= e(__('planner.form.notes_label')) ?></label>
                <input class="form-control" type="text" id="planner-notes" name="notas" maxlength="500"
                       value="<?= e((string) ($plan['notas'] ?? '')) ?>"
                       placeholder="<?= e(__('planner.form.notes_placeholder')) ?>">
            </div>
        </div>

        <?php if ($sections !== []): ?>
            <hr class="my-4">
            <h2 class="h6 mb-1"><?= e(__('planner.form.items_title')) ?></h2>
            <p class="small text-secondary mb-3"><?= e(__('planner.form.items_hint')) ?></p>

            <div class="d-flex flex-column gap-3">
                <?php foreach ($sections as $section): ?>
                    <?php
                    $sectionName = (string) $section['nombre'];
                    $isOrphan = $sectionName === '__sin_seccion';
                    $sectionItems = $itemsBySection[$sectionName] ?? [];
                    $label = $isOrphan ? __('planner.sections.sin_seccion') : $sectionName;
                    ?>
                    <section class="planner-section<?= !empty($section['items_excedido']) || !empty($section['excedido']) ? ' is-over' : '' ?>"
                             data-planner-section
                             data-percent="<?= e((string) (float) ($section['porcentaje'] ?? 0)) ?>"
                             data-assigned="<?= e($moneyValue((int) $section['asignado_centavos'])) ?>"
                             data-committed="<?= e($moneyValue((int) $section['comprometido_centavos'])) ?>"
                             data-currency="<?= e($currency) ?>">
                        <div class="planner-section-head">
                            <div class="planner-section-info">
                                <span class="planner-section-name">
                                    <i class="bi <?= e((string) ($section['icon'] ?? 'bi-dot')) ?> text-primary" aria-hidden="true"></i>
                                    <?= e((string) $label) ?>
                                </span>
                                <?php if (!$isOrphan && (float) ($section['porcentaje'] ?? 0) > 0): ?>
                                    <span class="planner-section-percent">
                                        <?= e(__('planner.sections.percent', [':percent' => $pct((float) $section['porcentaje'])])) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            <span class="planner-section-available<?= (int) $section['disponible_centavos'] < 0 ? ' is-negative' : '' ?>">
                                <span class="planner-section-available-label"><?= e(__('planner.sections.available')) ?></span>
                                <?= money_html((int) $section['disponible_centavos'], $currency) ?>
                            </span>
                        </div>

                        <div class="planner-editor" data-planner-editor>
                            <div class="d-flex flex-column gap-2" data-planner-rows>
                                <?php if ($sectionItems === []): ?>
                                    <?php $sectionItems = [['concepto' => '', 'monto_centavos' => 0]]; ?>
                                <?php endif; ?>
                                <?php foreach ($sectionItems as $item): ?>
                                    <?php $rowIndex = $index++; ?>
                                    <div class="planner-editor-row">
                                        <input class="form-control form-control-sm" type="text"
                                               name="items[<?= $rowIndex ?>][concepto]"
                                               value="<?= e((string) $item['concepto']) ?>"
                                               placeholder="<?= e(__('planner.form.item_concept')) ?>"
                                               maxlength="120" aria-label="<?= e(__('planner.form.item_concept')) ?>">
                                        <div class="input-group input-group-sm planner-amount-field">
                                            <span class="input-group-text"><?= e($currency) ?></span>
                                            <input class="form-control text-end" type="text" inputmode="decimal"
                                                   name="items[<?= $rowIndex ?>][monto]"
                                                   value="<?= (int) $item['monto_centavos'] > 0 ? e($moneyValue((int) $item['monto_centavos'])) : '' ?>"
                                                   placeholder="0.00" data-planner-amount data-money-input
                                                   autocomplete="off"
                                                   aria-label="<?= e(__('planner.form.item_amount')) ?>">
                                        </div>
                                        <input type="hidden" name="items[<?= $rowIndex ?>][seccion]" value="<?= e($sectionName) ?>">
                                        <button class="planner-item-remove" type="button" data-planner-remove
                                                aria-label="<?= e(__('planner.form.remove_item')) ?>">
                                            <i class="bi bi-x-lg" aria-hidden="true"></i>
                                        </button>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <template data-planner-row-template>
                                <div class="planner-editor-row">
                                    <input class="form-control form-control-sm" type="text"
                                           name="items[__INDEX__][concepto]"
                                           placeholder="<?= e(__('planner.form.item_concept')) ?>"
                                           maxlength="120" aria-label="<?= e(__('planner.form.item_concept')) ?>">
                                    <div class="input-group input-group-sm planner-amount-field">
                                        <span class="input-group-text"><?= e($currency) ?></span>
                                        <input class="form-control text-end" type="text" inputmode="decimal"
                                               name="items[__INDEX__][monto]" placeholder="0.00"
                                               data-planner-amount data-money-input autocomplete="off"
                                               aria-label="<?= e(__('planner.form.item_amount')) ?>">
                                    </div>
                                    <input type="hidden" name="items[__INDEX__][seccion]" value="<?= e($sectionName) ?>">
                                    <button class="planner-item-remove" type="button" data-planner-remove
                                            aria-label="<?= e(__('planner.form.remove_item')) ?>">
                                        <i class="bi bi-x-lg" aria-hidden="true"></i>
                                    </button>
                                </div>
                            </template>

                            <div class="planner-items-actions">
                                <button class="btn btn-sm btn-outline-secondary" type="button" data-planner-add>
                                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>
                                    <?= e(__('planner.form.add_item')) ?>
                                </button>
                            </div>
                        </div>

                        <div class="planner-section-foot">
                            <span><?= e(__('planner.sections.planned')) ?>: <span data-planner-planned>—</span></span>
                            <span><?= e(__('planner.sections.remaining')) ?>: <span data-planner-remaining>—</span></span>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="d-flex flex-wrap justify-content-end gap-2 mt-4">
            <a class="btn btn-outline-secondary" href="/planeador"><?= e(__('planner.form.cancel')) ?></a>
            <button class="btn btn-outline-primary" type="submit">
                <i class="bi bi-calculator me-1" aria-hidden="true"></i>
                <?= e(__('planner.form.calculate')) ?>
            </button>
            <button class="btn btn-primary" type="submit" formaction="<?= e($saveAction) ?>">
                <i class="bi bi-check2-circle me-1" aria-hidden="true"></i>
                <?= e(__($isEdit ? 'planner.form.save_changes' : 'planner.form.save')) ?>
            </button>
        </div>
    </form>
<?php endif; ?>
