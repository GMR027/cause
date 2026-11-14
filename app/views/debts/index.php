<?php
$debts = $debts ?? [];
$payments = $payments ?? [];
$strategy = (string) ($strategy ?? 'manual');
$strategies = $strategies ?? [];
$categories = $categories ?? [];
$defaultCategory = $default_category ?? ($categories[0]['nombre'] ?? '');
$currency = (string) ($currency ?? ($currentUser['moneda_activa'] ?? 'MXN'));
$totalOriginal = (int) ($total_original_centavos ?? 0);
$totalSaldo = (int) ($total_saldo_centavos ?? 0);
$totalPagado = (int) ($total_pagado_centavos ?? 0);
$totalProgreso = (int) ($total_progreso ?? 0);
$activeCount = (int) ($active_count ?? 0);
$dateLabel = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return '—';
    }

    return strlen($value) > 10 ? format_datetime($value) : format_date($value . ' 12:00:00');
};
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('debts.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('debts.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('debts.subtitle')) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?= partial('partials/lesson', ['lesson' => lesson_for('deudas')]) ?>
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="offcanvas" data-bs-target="#debtReminder">
            <i class="bi bi-bell me-1" aria-hidden="true"></i><?= e(__('debts.reminder.create')) ?>
        </button>
        <button type="button" class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#debtForm"
                data-debt-reset>
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= e(__('debts.create')) ?>
        </button>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-xl-3">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('debts.summary.original')) ?></span>
            <span class="finance-card-value"><?= money_html($totalOriginal, $currency) ?></span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('debts.summary.balance')) ?></span>
            <span class="finance-card-value"><?= money_html($totalSaldo, $currency) ?></span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('debts.summary.paid')) ?></span>
            <span class="finance-card-value"><?= money_html($totalPagado, $currency) ?></span>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="finance-card finance-card-accent">
            <span class="finance-card-label"><?= e(__('debts.summary.progress')) ?></span>
            <span class="finance-card-value"><?= $totalProgreso ?>%</span>
            <span class="finance-progress" aria-hidden="true"><span style="width: <?= $totalProgreso ?>%"></span></span>
            <span class="small text-secondary"><?= e(__('debts.summary.active', [':count' => (string) $activeCount])) ?></span>
        </div>
    </div>
</div>

<div class="surface-card mb-4">
    <form method="post" action="/deudas/estrategia" class="row g-2 align-items-end">
        <?= csrf_field() ?>
        <div class="col-12 col-md-8">
            <label class="form-label small" for="debt-strategy"><?= e(__('debts.strategy.title')) ?></label>
            <select class="form-select" id="debt-strategy" name="estrategia">
                <?php foreach ($strategies as $code => $label): ?>
                    <option value="<?= e($code) ?>" <?= $strategy === $code ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="form-text"><?= e(__('debts.strategy.hint')) ?></div>
        </div>
        <div class="col-12 col-md-4">
            <button type="submit" class="btn btn-outline-primary w-100"><?= e(__('debts.strategy.save')) ?></button>
        </div>
    </form>
</div>

<?php if ($debts === []): ?>
    <div class="surface-card text-center py-5">
        <i class="bi bi-credit-card fs-2 text-secondary" aria-hidden="true"></i>
        <p class="mt-2 mb-1 fw-semibold"><?= e(__('debts.empty')) ?></p>
        <p class="small text-secondary mb-3"><?= e(__('debts.empty_hint')) ?></p>
        <button type="button" class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#debtForm"
                data-debt-reset>
            <?= e(__('debts.create')) ?>
        </button>
    </div>
<?php else: ?>
    <?php if ($strategy !== 'manual'): ?>
        <?php foreach ($debts as $debt): ?>
            <?php if (!empty($debt['prioritaria'])): ?>
                <div class="alert alert-info py-2 small" role="status">
                    <i class="bi bi-bullseye me-1" aria-hidden="true"></i>
                    <?= e(__('debts.strategy.priority', [':name' => (string) $debt['nombre']])) ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="debts-grid mb-4">
        <?php foreach ($debts as $debt): ?>
            <?php
            $active = (int) $debt['activa'] === 1;
            $amountRaw = number_format((int) $debt['monto_total_centavos'] / 100, 2, '.', '');
            $balanceRaw = number_format((int) $debt['saldo_actual_centavos'] / 100, 2, '.', '');
            ?>
            <article class="debt-card <?= $active ? '' : 'is-closed' ?>">
                <div class="card-head mb-2">
                    <?= partial('partials/cover', [
                        'target' => 'deuda',
                        'id' => (int) $debt['id'],
                        'path' => $debt['portada_path'] ?? null,
                        'name' => (string) $debt['nombre'],
                        'icon' => 'credit-card',
                    ]) ?>
                    <div class="card-head-body">
                        <h2 class="h6 mb-1"><?= e((string) $debt['nombre']) ?></h2>
                        <div class="d-flex flex-wrap gap-1">
                            <?php if (!empty($debt['prioritaria'])): ?>
                                <span class="badge text-bg-primary"><?= e(__('debts.priority')) ?></span>
                            <?php endif; ?>
                            <?php if (!$active): ?>
                                <span class="badge text-bg-secondary"><?= e(__('debts.closed')) ?></span>
                            <?php elseif (!empty($debt['vencida'])): ?>
                                <span class="badge text-bg-danger"><?= e(__('debts.overdue')) ?></span>
                            <?php endif; ?>
                            <?php if ($debt['tasa_interes'] !== null): ?>
                                <span class="badge text-bg-light border"><?= e($debt['tasa_interes']) ?>%</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="fw-semibold text-nowrap"><?= money_html((int) $debt['saldo_actual_centavos'], (string) $debt['moneda']) ?></span>
                </div>

                <div class="finance-progress mb-2" aria-hidden="true">
                    <span style="width: <?= (int) $debt['progreso'] ?>%"></span>
                </div>
                <div class="d-flex justify-content-between small text-secondary mb-2">
                    <span><?= e(__('debts.card.paid', [':amount' => format_money((int) $debt['pagado_centavos'], (string) $debt['moneda'])])) ?></span>
                    <span><?= (int) $debt['progreso'] ?>%</span>
                </div>

                <dl class="row small mb-2">
                    <dt class="col-6 text-secondary fw-normal"><?= e(__('debts.fields.total')) ?></dt>
                    <dd class="col-6 text-end mb-1"><?= money_html((int) $debt['monto_total_centavos'], (string) $debt['moneda']) ?></dd>
                    <dt class="col-6 text-secondary fw-normal"><?= e(__('debts.fields.minimum')) ?></dt>
                    <dd class="col-6 text-end mb-1">
                        <?= $debt['pago_minimo_centavos'] !== null
                            ? money_html((int) $debt['pago_minimo_centavos'], (string) $debt['moneda'])
                            : '—' ?>
                    </dd>
                    <dt class="col-6 text-secondary fw-normal"><?= e(__('debts.fields.next')) ?></dt>
                    <dd class="col-6 text-end mb-0"><?= e($dateLabel($debt['proximo_pago'] ?? null)) ?></dd>
                </dl>

                <?php if (is_array($debt['recordatorio'] ?? null)): ?>
                    <p class="small text-secondary mb-2">
                        <i class="bi bi-bell me-1" aria-hidden="true"></i>
                        <?= e(__('debts.reminder.active', [
                            ':amount' => format_money((int) $debt['recordatorio']['monto_centavos'], (string) $debt['recordatorio']['moneda']),
                            ':date' => $dateLabel($debt['recordatorio']['proxima_ejecucion'] ?? null),
                        ])) ?>
                    </p>
                <?php endif; ?>

                <div class="d-flex flex-wrap gap-2 mt-auto">
                    <?php if ($active): ?>
                        <button type="button" class="btn btn-sm btn-primary" data-debt-pay
                                data-id="<?= (int) $debt['id'] ?>"
                                data-name="<?= e((string) $debt['nombre']) ?>"
                                data-balance="<?= e($balanceRaw) ?>"
                                data-bs-toggle="offcanvas" data-bs-target="#debtPayment">
                            <?= e(__('debts.pay')) ?>
                        </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-debt-edit
                            data-id="<?= (int) $debt['id'] ?>"
                            data-name="<?= e((string) $debt['nombre']) ?>"
                            data-total="<?= e($amountRaw) ?>"
                            data-balance="<?= e($balanceRaw) ?>"
                            data-rate="<?= e((string) ($debt['tasa_interes'] ?? '')) ?>"
                            data-minimum="<?= $debt['pago_minimo_centavos'] !== null ? e(number_format((int) $debt['pago_minimo_centavos'] / 100, 2, '.', '')) : '' ?>"
                            data-limit="<?= e((string) ($debt['fecha_limite'] ?? '')) ?>"
                            data-currency="<?= e((string) ($debt['moneda'] ?? 'MXN')) ?>"
                            data-bs-toggle="offcanvas" data-bs-target="#debtForm">
                        <?= e(__('common.actions.edit')) ?>
                    </button>
                    <form method="post" action="/deudas/eliminar" class="m-0"
                          onsubmit="return confirm('<?= e(__('debts.confirm_delete')) ?>')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $debt['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><?= e(__('common.actions.delete')) ?></button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="surface-card">
    <h2 class="h5 mb-3"><?= e(__('debts.payments.title')) ?></h2>
    <?php if ($payments === []): ?>
        <p class="small text-secondary mb-0"><?= e(__('debts.payments.empty')) ?></p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= e(__('debts.payments.date')) ?></th>
                        <th><?= e(__('debts.payments.debt')) ?></th>
                        <th class="text-end"><?= e(__('debts.payments.amount')) ?></th>
                        <th><?= e(__('debts.payments.note')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $payment): ?>
                        <tr>
                            <td class="small text-secondary"><?= e(format_datetime($payment['fecha'] ?? null)) ?></td>
                            <td><?= e((string) $payment['deuda_nombre']) ?></td>
                            <td class="text-end"><?= money_html((int) $payment['monto_centavos'], (string) $payment['moneda']) ?></td>
                            <td class="small text-secondary"><?= e((string) ($payment['nota'] ?? '—')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="debtForm" aria-labelledby="debtFormTitle">
    <div class="offcanvas-header">
        <h2 class="offcanvas-title h5" id="debtFormTitle"><?= e(__('debts.create')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/deudas" class="row g-3" data-debt-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">
            <div class="col-12">
                <label class="form-label" for="debt-name"><?= e(__('debts.fields.name')) ?></label>
                <input type="text" class="form-control" id="debt-name" name="nombre" maxlength="120" required>
            </div>
            <div class="col-6">
                <label class="form-label" for="debt-total"><?= e(__('debts.fields.total')) ?></label>
                <input type="text" inputmode="decimal" class="form-control" id="debt-total" name="monto_total" required>
            </div>
            <div class="col-6">
                <label class="form-label" for="debt-balance"><?= e(__('debts.fields.balance')) ?></label>
                <input type="text" inputmode="decimal" class="form-control" id="debt-balance" name="saldo_actual" required>
            </div>
            <div class="col-6">
                <label class="form-label" for="debt-rate"><?= e(__('debts.fields.rate')) ?></label>
                <input type="text" inputmode="decimal" class="form-control" id="debt-rate" name="tasa_interes"
                       placeholder="<?= e(__('debts.fields.optional')) ?>">
            </div>
            <div class="col-6">
                <label class="form-label" for="debt-minimum"><?= e(__('debts.fields.minimum')) ?></label>
                <input type="text" inputmode="decimal" class="form-control" id="debt-minimum" name="pago_minimo"
                       placeholder="<?= e(__('debts.fields.optional')) ?>">
            </div>
            <div class="col-12">
                <?= partial('partials/currency-select', [
                    'name' => 'moneda',
                    'selected' => (is_string($_POST['moneda'] ?? null) ? $_POST['moneda'] : (string) ($debt['moneda'] ?? ($currentUser['moneda_activa'] ?? 'MXN'))),
                ]) ?>
            </div>
            <div class="col-12">
                <label class="form-label" for="debt-limit"><?= e(__('debts.fields.limit')) ?></label>
                <input type="date" class="form-control" id="debt-limit" name="fecha_limite">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><?= e(__('common.actions.save')) ?></button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">
                    <?= e(__('common.actions.cancel')) ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="debtPayment" aria-labelledby="debtPaymentTitle">
    <div class="offcanvas-header">
        <div>
            <span class="small text-secondary d-block"><?= e(__('debts.pay')) ?></span>
            <h2 class="offcanvas-title h5 mb-0" id="debtPaymentTitle"><?= e(__('debts.payment.title')) ?></h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/deudas/pagar" class="row g-3">
            <?= csrf_field() ?>
            <input type="hidden" name="deuda_id" value="">
            <div class="col-12">
                <p class="fw-semibold mb-0" data-debt-pay-name></p>
                <p class="small text-secondary mb-0" data-debt-pay-balance></p>
            </div>
            <div class="col-12">
                <label class="form-label" for="debt-pay-amount"><?= e(__('debts.payment.amount')) ?></label>
                <input type="text" inputmode="decimal" class="form-control" id="debt-pay-amount" name="monto" required>
            </div>
            <div class="col-12">
                <label class="form-label" for="debt-pay-date"><?= e(__('debts.payment.date')) ?></label>
                <input type="date" class="form-control" id="debt-pay-date" name="fecha"
                       value="<?= e((new DateTimeImmutable('now'))->format('Y-m-d')) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label" for="debt-pay-note"><?= e(__('debts.payment.note')) ?></label>
                <input type="text" class="form-control" id="debt-pay-note" name="nota" maxlength="255">
            </div>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="debt-register-expense" name="registrar_gasto" value="1">
                    <label class="form-check-label" for="debt-register-expense"><?= e(__('debts.payment.register')) ?></label>
                </div>
                <p class="small text-secondary mt-1 mb-0"><?= e(__('debts.payment.register_hint')) ?></p>
            </div>
            <div class="col-12" data-debt-category-wrap hidden>
                <label class="form-label" for="debt-pay-category"><?= e(__('debts.payment.category')) ?></label>
                <select class="form-select" id="debt-pay-category" name="parte_metodo">
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['nombre']) ?>"
                                <?= (string) $category['nombre'] === (string) $defaultCategory ? 'selected' : '' ?>>
                            <?= e((string) $category['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><?= e(__('debts.payment.submit')) ?></button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">
                    <?= e(__('common.actions.cancel')) ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="debtReminder" aria-labelledby="debtReminderTitle">
    <div class="offcanvas-header">
        <div>
            <span class="small text-secondary d-block"><?= e(__('debts.reminder.create')) ?></span>
            <h2 class="offcanvas-title h5 mb-0" id="debtReminderTitle"><?= e(__('debts.reminder.title')) ?></h2>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <p class="small text-secondary"><?= e(__('debts.reminder.hint')) ?></p>
        <form method="post" action="/deudas/recordatorio" class="row g-3">
            <?= csrf_field() ?>
            <div class="col-12">
                <label class="form-label" for="debt-reminder-debt"><?= e(__('debts.payments.debt')) ?></label>
                <select class="form-select" id="debt-reminder-debt" name="deuda_id" required>
                    <?php foreach ($debts as $debt): ?>
                        <?php if ((int) $debt['activa'] === 1): ?>
                            <option value="<?= (int) $debt['id'] ?>"><?= e((string) $debt['nombre']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6">
                <label class="form-label" for="debt-reminder-amount"><?= e(__('debts.reminder.amount')) ?></label>
                <input type="text" inputmode="decimal" class="form-control" id="debt-reminder-amount" name="monto" required>
            </div>
            <div class="col-6">
                <label class="form-label" for="debt-reminder-recurrence"><?= e(__('debts.reminder.recurrence')) ?></label>
                <select class="form-select" id="debt-reminder-recurrence" name="recurrencia">
                    <?php foreach (['diaria', 'semanal', 'mensual', 'anual'] as $recurrence): ?>
                        <option value="<?= e($recurrence) ?>" <?= $recurrence === 'mensual' ? 'selected' : '' ?>>
                            <?= e(__('onboarding.recurrencia.' . $recurrence)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="debt-reminder-date"><?= e(__('debts.reminder.next')) ?></label>
                <input type="date" class="form-control" id="debt-reminder-date" name="proxima_ejecucion"
                       value="<?= e((new DateTimeImmutable('now'))->format('Y-m-d')) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label" for="debt-reminder-category"><?= e(__('debts.reminder.category')) ?></label>
                <select class="form-select" id="debt-reminder-category" name="parte_metodo">
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= e((string) $category['nombre']) ?>"
                                <?= (string) $category['nombre'] === (string) $defaultCategory ? 'selected' : '' ?>>
                            <?= e((string) $category['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1"><?= e(__('debts.reminder.save')) ?></button>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">
                    <?= e(__('common.actions.cancel')) ?>
                </button>
            </div>
        </form>
    </div>
</div>
