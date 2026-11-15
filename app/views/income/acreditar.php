<?php

$ingreso = $ingreso ?? [];
$asignaciones = $asignaciones ?? [];
$categoryTypes = $categoryTypes ?? [];
$method = $method ?? null;
$recurring = $recurring ?? [];
$pendingRecurring = $pendingRecurring ?? [];
$debts = $debts ?? [];
$debtCategories = $debtCategories ?? [];
$metas = $metas ?? [];
$bolsas = $bolsas ?? [];
$wishes = $wishes ?? [];
$errors = $errors ?? [];
$currency = (string) ($ingreso['moneda'] ?? 'MXN');
$principal = (string) ($currentUser['moneda_activa'] ?? $currency);
$methodKey = $method !== null ? 'onboarding.methods.' . (string) $method['codigo'] . '.name' : '';
$methodName = $methodKey !== '' ? __($methodKey) : '';
$methodName = $methodName === $methodKey ? (string) ($method['nombre'] ?? '') : $methodName;
$total = array_sum(array_map(static fn (array $row): int => (int) $row['monto_centavos'], $asignaciones));
$hasPayments = $debts !== [] || $metas !== [] || $bolsas !== [] || $wishes !== [];
$money = static fn (int $cents, string $code): string => format_money($cents, $code);
$sectionIcons = [
    'controlada' => 'bi-cart',
    'ahorro' => 'bi-piggy-bank',
    'inversion' => 'bi-graph-up-arrow',
    'donacion' => 'bi-heart',
    'compartir' => 'bi-people',
];
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

    <form method="post" action="/ingresos/acreditar" data-credit-form data-available="<?= (int) $total ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="ingreso_id" value="<?= (int) ($ingreso['id'] ?? 0) ?>">

        <h2 class="h6 text-uppercase text-secondary mt-3 mb-2"><?= e(__('income.acreditar.split_title')) ?></h2>
        <div class="allocation-list">
            <?php foreach ($asignaciones as $asignacion): ?>
                <?php $type = (string) ($categoryTypes[(string) $asignacion['categoria']] ?? 'controlada'); ?>
                <div class="allocation-card">
                    <div class="allocation-head">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi <?= e($sectionIcons[$type] ?? 'bi-dot') ?> text-primary" aria-hidden="true"></i>
                            <div>
                                <span class="allocation-name"><?= e((string) $asignacion['categoria']) ?></span>
                                <span class="small text-secondary d-block">
                                    <?= e(__('income.types_category.' . $type)) ?>
                                </span>
                            </div>
                        </div>
                        <span class="d-flex flex-column align-items-end">
                            <span class="allocation-amount"><?= money_html((int) $asignacion['monto_centavos'], $currency) ?></span>
                            <span class="badge text-bg-success-subtle text-success-emphasis"><?= e(__('income.acreditar.available_badge')) ?></span>
                        </span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($recurring !== [] || $pendingRecurring !== []): ?>
            <div class="alert alert-warning mt-3" role="alert">
                <div class="d-flex gap-2 mb-2">
                    <i class="bi bi-arrow-repeat" aria-hidden="true"></i>
                    <div>
                        <strong class="d-block"><?= e(__('income.acreditar.recurring_title')) ?></strong>
                        <span class="small"><?= e(__('income.acreditar.recurring_body')) ?></span>
                    </div>
                </div>

                <div class="d-flex flex-column gap-2">
                    <?php foreach ($pendingRecurring as $gasto): ?>
                        <?php $gid = (int) $gasto['id']; ?>
                        <div class="recurring-decision">
                            <div class="flex-grow-1">
                                <span class="fw-semibold d-block"><?= e((string) ($gasto['nota'] ?? $gasto['parte_metodo'])) ?></span>
                                <span class="small"><?= e(__('income.acreditar.recurring_pending', [
                                    ':date' => format_date($gasto['fecha'] ?? null),
                                    ':part' => (string) $gasto['parte_metodo'],
                                ])) ?></span>
                            </div>
                            <span class="fw-semibold"><?= money_html((int) $gasto['monto_centavos'], (string) $gasto['moneda']) ?></span>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="gastos_recurrentes[<?= $gid ?>]" value="aceptar"
                                       id="gr_<?= $gid ?>_a" checked>
                                <label class="btn btn-outline-secondary" for="gr_<?= $gid ?>_a"><?= e(__('income.acreditar.recurring_accept')) ?></label>
                                <input type="radio" class="btn-check" name="gastos_recurrentes[<?= $gid ?>]" value="no_controlado"
                                       id="gr_<?= $gid ?>_n">
                                <label class="btn btn-outline-warning" for="gr_<?= $gid ?>_n"><?= e(__('income.acreditar.recurring_no_controlado')) ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <?php foreach ($recurring as $item): ?>
                        <?php $rid = (int) $item['id']; ?>
                        <div class="recurring-decision">
                            <div class="flex-grow-1">
                                <span class="fw-semibold d-block"><?= e((string) $item['nombre']) ?></span>
                                <span class="small"><?= e(__('income.acreditar.recurring_next', [
                                    ':date' => format_date($item['proxima_ejecucion'] ?? null),
                                    ':part' => (string) $item['parte_metodo'],
                                ])) ?></span>
                            </div>
                            <span class="fw-semibold"><?= money_html((int) $item['monto_centavos'], (string) $item['moneda']) ?></span>
                            <div class="btn-group btn-group-sm" role="group">
                                <input type="radio" class="btn-check" name="recurrentes[<?= $rid ?>]" value="aceptar"
                                       id="r_<?= $rid ?>_a" checked>
                                <label class="btn btn-outline-secondary" for="r_<?= $rid ?>_a"><?= e(__('income.acreditar.recurring_accept')) ?></label>
                                <input type="radio" class="btn-check" name="recurrentes[<?= $rid ?>]" value="no_controlado"
                                       id="r_<?= $rid ?>_n">
                                <label class="btn btn-outline-warning" for="r_<?= $rid ?>_n"><?= e(__('income.acreditar.recurring_no_controlado')) ?></label>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <details class="payments-panel mt-3" <?= $hasPayments ? '' : 'hidden' ?>>
            <summary class="d-flex align-items-center justify-content-between gap-2">
                <span>
                    <strong class="d-block"><?= e(__('income.acreditar.payments_title')) ?></strong>
                    <span class="small text-secondary"><?= e(__('income.acreditar.payments_body')) ?></span>
                </span>
                <i class="bi bi-chevron-down" aria-hidden="true"></i>
            </summary>

            <?php
            $groups = [
                ['key' => 'deuda', 'title' => 'payments_debts', 'icon' => 'bi-credit-card', 'rows' => array_map(static fn (array $row): array => [
                    'id' => (int) $row['id'],
                    'name' => (string) $row['nombre'],
                    'hint' => __('income.acreditar.payments_debt_balance', [':amount' => $money((int) $row['saldo_actual_centavos'], (string) $row['moneda'])]),
                    'currency' => (string) $row['moneda'],
                    'max' => (int) $row['saldo_actual_centavos'],
                ], $debts)],
                ['key' => 'meta', 'title' => 'payments_goals', 'icon' => 'bi-bullseye', 'rows' => array_map(static fn (array $row): array => [
                    'id' => (int) $row['id'],
                    'name' => (string) $row['nombre'],
                    'hint' => (int) $row['objetivo_centavos'] > 0
                        ? __('income.acreditar.payments_goal_progress', [':amount' => $money((int) $row['objetivo_centavos'], (string) $row['moneda'])])
                        : (string) ($row['cuenta_nombre'] ?? ''),
                    'currency' => (string) ($row['cuenta_moneda'] ?? $row['moneda']),
                    'max' => null,
                ], $metas)],
                ['key' => 'bolsa', 'title' => 'payments_bags', 'icon' => 'bi-piggy-bank', 'rows' => array_map(static fn (array $row): array => [
                    'id' => (int) $row['id'],
                    'name' => (string) $row['nombre'],
                    'hint' => __('income.acreditar.payments_bag_balance', [':amount' => $money((int) $row['saldo_centavos'], (string) $row['moneda'])]),
                    'currency' => (string) $row['moneda'],
                    'max' => null,
                ], $bolsas)],
                ['key' => 'deseo', 'title' => 'payments_wishes', 'icon' => 'bi-stars', 'rows' => array_map(static fn (array $row): array => [
                    'id' => (int) $row['id'],
                    'name' => (string) $row['nombre'],
                    'hint' => __('income.acreditar.payments_wish_price', [':amount' => $money((int) $row['monto_estimado_centavos'], (string) ($row['moneda'] ?? $principal))]),
                    'currency' => $principal,
                    'max' => null,
                ], $wishes)],
            ];
            ?>

            <div class="d-flex flex-column gap-3 mt-3">
                <?php foreach ($groups as $group): ?>
                    <?php if ($group['rows'] === []) {
                        continue;
                    } ?>
                    <div>
                        <h3 class="h6 d-flex align-items-center gap-2 mb-2">
                            <i class="bi <?= e($group['icon']) ?>" aria-hidden="true"></i>
                            <?= e(__('income.acreditar.' . $group['title'])) ?>
                        </h3>

                        <?php if ($group['key'] === 'deuda' && $debtCategories !== []): ?>
                            <div class="mb-2">
                                <label class="form-label small mb-1" for="deuda_categoria"><?= e(__('income.acreditar.payments_debt_category')) ?></label>
                                <select class="form-select form-select-sm" id="deuda_categoria" name="deuda_categoria">
                                    <?php foreach ($debtCategories as $category): ?>
                                        <option value="<?= e((string) $category['nombre']) ?>"><?= e((string) $category['nombre']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex flex-column gap-2">
                            <?php foreach ($group['rows'] as $row): ?>
                                <?php $fieldId = 'abono_' . $group['key'] . '_' . $row['id']; ?>
                                <div class="payment-row">
                                    <label class="flex-grow-1 mb-0" for="<?= e($fieldId) ?>">
                                        <span class="fw-semibold d-block"><?= e($row['name']) ?></span>
                                        <?php if ($row['hint'] !== ''): ?>
                                            <span class="small text-secondary"><?= e($row['hint']) ?></span>
                                        <?php endif; ?>
                                    </label>
                                    <div class="input-group input-group-sm payment-input">
                                        <span class="input-group-text"><?= e($row['currency']) ?></span>
                                        <input type="number" class="form-control" id="<?= e($fieldId) ?>"
                                               name="abonos[<?= e($group['key']) ?>][<?= (int) $row['id'] ?>]"
                                               min="0" step="0.01" inputmode="decimal" placeholder="0.00"
                                               <?= $row['max'] !== null ? 'max="' . e(number_format($row['max'] / 100, 2, '.', '')) . '"' : '' ?>
                                               data-payment-input>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </details>

        <div class="allocation-summary">
            <div>
                <span class="small text-secondary d-block"><?= e(__('income.acreditar.payments_total')) ?></span>
                <span class="fw-semibold" data-payment-total><?= e(format_money(0, $currency)) ?></span>
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
