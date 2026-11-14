<?php

$ingreso = $ingreso ?? [];
$asignaciones = $asignaciones ?? [];
$method = $method ?? null;
$reasons = $reasons ?? [];
$errors = $errors ?? [];
$old = $old ?? [];
$currency = (string) ($ingreso['moneda'] ?? 'MXN');
$methodKey = $method !== null ? 'onboarding.methods.' . (string) $method['codigo'] . '.name' : '';
$methodName = $methodKey !== '' ? __($methodKey) : '';
$methodName = $methodName === $methodKey ? (string) ($method['nombre'] ?? '') : $methodName;
$total = array_sum(array_map(static fn (array $row): int => (int) $row['monto_centavos'], $asignaciones));
?>
<div class="flow-card surface-card">
    <span class="badge phase-badge mb-2"><?= e(__('income.acreditar.badge')) ?></span>
    <h1 class="h4 mb-1"><?= e(__('income.acreditar.heading')) ?></h1>
    <p class="text-secondary mb-3"><?= e(__('income.acreditar.subtitle')) ?></p>

    <div class="income-summary">
        <div>
            <span class="small text-secondary d-block"><?= e(__('income.acreditar.amount')) ?></span>
            <span class="income-summary-amount"><?= money_html((int) ($ingreso['monto_centavos'] ?? 0), $currency) ?></span>
        </div>
        <div>
            <span class="small text-secondary d-block"><?= e(__('income.acreditar.method')) ?></span>
            <span class="fw-semibold"><?= e($methodName) ?></span>
        </div>
        <div>
            <span class="small text-secondary d-block"><?= e(__('income.acreditar.period')) ?></span>
            <span class="fw-semibold">
                <?= e(format_date($ingreso['periodo_inicio'] ?? null)) ?> – <?= e(format_date($ingreso['periodo_fin'] ?? null)) ?>
            </span>
        </div>
    </div>

    <?= partial('partials/errors', ['errors' => $errors]) ?>

    <form method="post" action="/ingresos/acreditar" data-allocation-form>
        <?= csrf_field() ?>
        <input type="hidden" name="ingreso_id" value="<?= (int) ($ingreso['id'] ?? 0) ?>">

        <div class="allocation-list">
            <?php foreach ($asignaciones as $asignacion): ?>
                <?php
                $id = (int) $asignacion['id'];
                $previous = is_array($old[$id] ?? null) ? $old[$id] : [];
                $estado = (string) ($previous['estado'] ?? '');
                $motivo = (string) ($previous['motivo'] ?? '');
                $texto = (string) ($previous['texto'] ?? '');
                ?>
                <div class="allocation-card" data-allocation>
                    <div class="allocation-head">
                        <div>
                            <span class="allocation-name"><?= e((string) $asignacion['categoria']) ?></span>
                            <span class="small text-secondary d-block"><?= e(__('income.acreditar.category_hint')) ?></span>
                        </div>
                        <span class="allocation-amount">
                            <?= money_html((int) $asignacion['monto_centavos'], $currency) ?>
                        </span>
                    </div>

                    <div class="allocation-states">
                        <?php foreach (['acreditado', 'justificado', 'no_controlado'] as $option): ?>
                            <label class="allocation-state">
                                <input type="radio" name="decisiones[<?= $id ?>][estado]"
                                       value="<?= e($option) ?>" <?= $estado === $option ? 'checked' : '' ?> required>
                                <span><?= e(__('income.states.' . $option)) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div class="justify-fields <?= $estado === 'justificado' ? '' : 'd-none' ?>" data-justify>
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <label class="form-label small" for="motivo_<?= $id ?>">
                                    <?= e(__('income.acreditar.reason')) ?>
                                </label>
                                <select class="form-select" id="motivo_<?= $id ?>"
                                        name="decisiones[<?= $id ?>][motivo]" data-justify-reason>
                                    <option value=""><?= e(__('income.acreditar.reason_choose')) ?></option>
                                    <?php foreach ($reasons as $reason): ?>
                                        <option value="<?= e((string) $reason) ?>" <?= $motivo === $reason ? 'selected' : '' ?>>
                                            <?= e(__('income.reasons.' . (string) $reason)) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label small" for="texto_<?= $id ?>">
                                    <?= e(__('income.acreditar.reason_text')) ?>
                                </label>
                                <input type="text" class="form-control" id="texto_<?= $id ?>"
                                       name="decisiones[<?= $id ?>][texto]" maxlength="255"
                                       value="<?= e($texto) ?>" placeholder="<?= e(__('income.acreditar.reason_placeholder')) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="allocation-summary">
            <div>
                <span class="small text-secondary d-block"><?= e(__('income.acreditar.summary')) ?></span>
                <span class="allocation-summary-text" data-allocation-summary>
                    <?= e(__('income.acreditar.summary_empty')) ?>
                </span>
            </div>
            <div class="text-end">
                <span class="small text-secondary d-block"><?= e(__('income.acreditar.total')) ?></span>
                <span class="fw-bold"><?= money_html($total, $currency) ?></span>
            </div>
        </div>

        <div class="flow-actions">
            <a href="/inicio" class="btn btn-outline-secondary"><?= e(__('common.actions.cancel')) ?></a>
            <button type="submit" class="btn btn-primary"><?= e(__('income.acreditar.save')) ?></button>
        </div>
    </form>
</div>
