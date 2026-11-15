<?php
$subscriptions = $subscriptions ?? [];
$cancelled = $cancelled ?? [];
$upcoming = $upcoming ?? [];
$trials = $trials ?? [];
$categories = $categories ?? [];
$today = (string) ($today ?? date('Y-m-d'));
$currency = (string) ($currency ?? ($currentUser['moneda_activa'] ?? 'MXN'));
$annualTotal = (int) ($annual_total_centavos ?? 0);
$monthlyTotal = (int) ($monthly_total_centavos ?? 0);
$cancelledSavings = (int) ($cancelled_savings_centavos ?? 0);
$count = (int) ($count ?? 0);
$frequencies = ['mensual', 'anual', 'semanal', 'diaria'];
$whenLabel = static function (?int $days): string {
    if ($days === null) {
        return '';
    }

    return match (true) {
        $days < 0 => __('reports.subscriptions.when.overdue'),
        $days === 0 => __('reports.subscriptions.when.today'),
        $days === 1 => __('reports.subscriptions.when.tomorrow'),
        default => __('reports.subscriptions.when.in_days', [':days' => (string) $days]),
    };
};
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('reports.subscriptions.title')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('reports.subscriptions.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('reports.subscriptions.subtitle')) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="button" class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#subscriptionForm"
                data-subscription-new>
            <i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= e(__('reports.subscriptions.add')) ?>
        </button>
        <a href="/reportes" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1" aria-hidden="true"></i><?= e(__('nav.reportes')) ?>
        </a>
    </div>
</div>

<div class="alert alert-secondary py-2 small" role="status" data-dismissible data-dismiss-key="subscriptions-info">
    <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
    <?= e(__('reports.subscriptions.hint')) ?>
</div>

<?php if ($subscriptions === [] && $cancelled === []): ?>
    <div class="surface-card text-center py-5">
        <i class="bi bi-arrow-repeat fs-2 text-secondary" aria-hidden="true"></i>
        <p class="mt-2 mb-1 fw-semibold"><?= e(__('reports.subscriptions.empty')) ?></p>
        <p class="small text-secondary mb-3"><?= e(__('reports.subscriptions.empty_hint')) ?></p>
        <button type="button" class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#subscriptionForm"
                data-subscription-new>
            <?= e(__('reports.subscriptions.add')) ?>
        </button>
    </div>
<?php else: ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-3">
            <div class="finance-card">
                <span class="finance-card-label"><?= e(__('reports.subscriptions.active')) ?></span>
                <span class="finance-card-value"><?= $count ?></span>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="finance-card">
                <span class="finance-card-label"><?= e(__('reports.subscriptions.monthly')) ?></span>
                <span class="finance-card-value"><?= money_html($monthlyTotal, $currency) ?></span>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="finance-card finance-card-accent">
                <span class="finance-card-label"><?= e(__('reports.subscriptions.annual')) ?></span>
                <span class="finance-card-value"><?= money_html($annualTotal, $currency) ?></span>
            </div>
        </div>
        <div class="col-6 col-xl-3">
            <div class="finance-card">
                <span class="finance-card-label"><?= e(__('reports.subscriptions.saved_cancelling')) ?></span>
                <span class="finance-card-value text-success"><?= money_html($cancelledSavings, $currency) ?></span>
                <span class="small text-secondary"><?= e(__('reports.subscriptions.per_year')) ?></span>
            </div>
        </div>
    </div>

    <?php if ($upcoming !== [] || $trials !== []): ?>
        <div class="row g-3 mb-4">
            <?php if ($upcoming !== []): ?>
                <div class="col-12 col-lg-6">
                    <div class="surface-card h-100">
                        <h2 class="h6 mb-2"><i class="bi bi-calendar-event me-1" aria-hidden="true"></i><?= e(__('reports.subscriptions.upcoming')) ?></h2>
                        <ul class="list-unstyled mb-0 small">
                            <?php foreach ($upcoming as $item): ?>
                                <li class="d-flex justify-content-between gap-2 py-1">
                                    <span><strong><?= e($item['name']) ?></strong> · <?= e($whenLabel($item['days_to_next'])) ?></span>
                                    <span><?= money_html((int) $item['amount_centavos'], (string) $item['currency']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
            <?php if ($trials !== []): ?>
                <div class="col-12 col-lg-6">
                    <div class="surface-card h-100 border-warning">
                        <h2 class="h6 mb-2"><i class="bi bi-hourglass-split me-1" aria-hidden="true"></i><?= e(__('reports.subscriptions.trials')) ?></h2>
                        <ul class="list-unstyled mb-0 small">
                            <?php foreach ($trials as $item): ?>
                                <li class="d-flex justify-content-between gap-2 py-1">
                                    <span><strong><?= e($item['name']) ?></strong></span>
                                    <span class="text-warning-emphasis"><?= e(__('reports.subscriptions.trial_ends', [
                                        ':when' => $whenLabel($item['trial_days']),
                                    ])) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($subscriptions !== []): ?>
        <div class="surface-card mb-4">
            <h2 class="h5 mb-3"><?= e(__('reports.subscriptions.list_title')) ?></h2>
            <div class="subscription-list">
                <?php foreach ($subscriptions as $item): ?>
                    <div class="subscription-row">
                        <input class="form-check-input mt-1" type="checkbox" data-subscription
                               value="<?= (int) $item['annual_centavos'] ?>"
                               aria-label="<?= e(__('reports.subscriptions.select', [':name' => $item['name']])) ?>">
                        <div class="flex-grow-1 min-w-0">
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="fw-semibold"><?= e($item['name']) ?></span>
                                <?php if ($item['provider'] !== null): ?>
                                    <span class="small text-secondary"><?= e($item['provider']) ?></span>
                                <?php endif; ?>
                                <?php if (!$item['registered']): ?>
                                    <span class="badge text-bg-light border"><?= e(__('reports.subscriptions.detected')) ?></span>
                                <?php endif; ?>
                                <?php if ($item['duplicate']): ?>
                                    <span class="badge text-bg-warning"><?= e(__('reports.subscriptions.duplicate')) ?></span>
                                <?php endif; ?>
                                <?php if ($item['variable']): ?>
                                    <span class="badge text-bg-light border"><?= e(__('reports.subscriptions.variable')) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="small text-secondary">
                                <?= e(__('reports.frequencies.' . $item['frequency'])) ?> · <?= e($item['category']) ?>
                                <?php if ($item['next'] !== null): ?>
                                    · <?= e(__('reports.subscriptions.next_charge', [
                                        ':date' => format_date($item['next'] . ' 12:00:00'),
                                        ':when' => $whenLabel($item['days_to_next']),
                                    ])) ?>
                                <?php endif; ?>
                                <?php if ($item['reminder_days'] !== null): ?>
                                    · <i class="bi bi-bell" aria-hidden="true"></i>
                                    <?= e(__('reports.subscriptions.reminder_short', [':days' => (string) $item['reminder_days']])) ?>
                                <?php endif; ?>
                            </div>
                            <?php if ($item['notes'] !== null): ?>
                                <div class="small fst-italic text-secondary"><?= e($item['notes']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold"><?= money_html((int) $item['amount_centavos'], (string) $item['currency']) ?></div>
                            <div class="small text-secondary"><?= e(__('reports.subscriptions.annual_short', [
                                ':amount' => format_money((int) $item['annual_centavos'], $currency),
                            ])) ?></div>
                        </div>
                        <div class="d-flex gap-1">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-subscription-edit
                                    data-bs-toggle="offcanvas" data-bs-target="#subscriptionForm"
                                    data-item="<?= e((string) json_encode($item, JSON_UNESCAPED_UNICODE)) ?>"
                                    aria-label="<?= e(__('common.actions.edit')) ?>">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </button>
                            <form method="post" action="/reportes/suscripciones/cancelar" class="m-0"
                                  data-confirm="<?= e(__('reports.subscriptions.cancel_confirm', [':name' => $item['name']])) ?>"
                                  data-confirm-danger>
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        aria-label="<?= e(__('reports.subscriptions.cancel')) ?>"
                                        title="<?= e(__('reports.subscriptions.cancel')) ?>">
                                    <i class="bi bi-x-circle" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="surface-card mb-4">
            <h2 class="h5 mb-2"><?= e(__('reports.subscriptions.savings_title')) ?></h2>
            <p class="small text-secondary mb-2"><?= e(__('reports.subscriptions.savings_hint')) ?></p>
            <p class="mb-0">
                <strong data-subscription-total>—</strong>
                <span class="d-block small" data-subscription-message><?= e(__('reports.subscriptions.savings_none')) ?></span>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($cancelled !== []): ?>
        <div class="surface-card">
            <h2 class="h5 mb-3"><?= e(__('reports.subscriptions.cancelled_title')) ?></h2>
            <div class="subscription-list">
                <?php foreach ($cancelled as $item): ?>
                    <div class="subscription-row is-cancelled">
                        <div class="flex-grow-1">
                            <span class="fw-semibold d-block"><?= e($item['name']) ?></span>
                            <span class="small text-secondary"><?= e(__('reports.subscriptions.cancelled_on', [
                                ':date' => format_date($item['cancelled_at']),
                                ':amount' => format_money((int) $item['annual_centavos'], $currency),
                            ])) ?></span>
                        </div>
                        <form method="post" action="/reportes/suscripciones/reactivar" class="d-flex gap-2 align-items-center m-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                            <label class="visually-hidden" for="reactivate_<?= (int) $item['id'] ?>"><?= e(__('reports.subscriptions.form.next')) ?></label>
                            <input type="date" class="form-control form-control-sm" id="reactivate_<?= (int) $item['id'] ?>"
                                   name="proxima_ejecucion" min="<?= e($today) ?>" value="<?= e($today) ?>" required>
                            <button type="submit" class="btn btn-sm btn-outline-secondary text-nowrap"><?= e(__('reports.subscriptions.reactivate')) ?></button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="subscriptionForm" aria-labelledby="subscriptionFormTitle">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="subscriptionFormTitle"
            data-title-new="<?= e(__('reports.subscriptions.add')) ?>"
            data-title-edit="<?= e(__('reports.subscriptions.edit')) ?>"><?= e(__('reports.subscriptions.add')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <?php if ($categories === []): ?>
            <p class="text-secondary mb-0"><?= e(__('settings.ingreso.no_method')) ?></p>
        <?php else: ?>
            <form method="post" action="/reportes/suscripciones" data-subscription-form
                  data-action-new="/reportes/suscripciones" data-action-edit="/reportes/suscripciones/editar">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="">
                <div class="row g-2">
                    <div class="col-12 col-sm-7">
                        <label class="form-label small" for="sub_name"><?= e(__('reports.subscriptions.form.name')) ?></label>
                        <input type="text" class="form-control" id="sub_name" name="nombre" maxlength="120" required
                               placeholder="<?= e(__('reports.subscriptions.form.name_placeholder')) ?>">
                    </div>
                    <div class="col-12 col-sm-5">
                        <label class="form-label small" for="sub_provider"><?= e(__('reports.subscriptions.form.provider')) ?></label>
                        <input type="text" class="form-control" id="sub_provider" name="proveedor" maxlength="80"
                               placeholder="<?= e(__('reports.subscriptions.form.provider_placeholder')) ?>">
                    </div>
                    <div class="col-7">
                        <label class="form-label small" for="sub_amount"><?= e(__('reports.subscriptions.form.amount')) ?></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" class="form-control" id="sub_amount" name="monto" min="0.01" step="0.01"
                                   inputmode="decimal" required>
                        </div>
                    </div>
                    <div class="col-5" data-subscription-currency>
                        <?= partial('partials/currency-select', ['name' => 'moneda', 'selected' => $currency]) ?>
                    </div>
                    <div class="col-6">
                        <label class="form-label small" for="sub_frequency"><?= e(__('reports.subscriptions.frequency')) ?></label>
                        <select class="form-select" id="sub_frequency" name="recurrencia">
                            <?php foreach ($frequencies as $frequency): ?>
                                <option value="<?= e($frequency) ?>"><?= e(__('reports.frequencies.' . $frequency)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small" for="sub_next"><?= e(__('reports.subscriptions.form.next')) ?></label>
                        <input type="date" class="form-control" id="sub_next" name="proxima_ejecucion" min="<?= e($today) ?>"
                               value="<?= e($today) ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label small" for="sub_category"><?= e(__('reports.subscriptions.form.category')) ?></label>
                        <select class="form-select" id="sub_category" name="parte_metodo">
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= e((string) $category['nombre']) ?>"><?= e((string) $category['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small" for="sub_trial"><?= e(__('reports.subscriptions.form.trial')) ?></label>
                        <input type="date" class="form-control" id="sub_trial" name="prueba_hasta">
                    </div>
                    <div class="col-6">
                        <label class="form-label small" for="sub_reminder"><?= e(__('reports.subscriptions.form.reminder')) ?></label>
                        <select class="form-select" id="sub_reminder" name="recordatorio_dias">
                            <option value=""><?= e(__('reports.subscriptions.form.reminder_none')) ?></option>
                            <?php foreach ([1, 2, 3, 5, 7] as $days): ?>
                                <option value="<?= $days ?>" <?= $days === 3 ? 'selected' : '' ?>>
                                    <?= e(__('reports.subscriptions.form.reminder_days', [':days' => (string) $days])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small" for="sub_notes"><?= e(__('reports.subscriptions.form.notes')) ?></label>
                        <input type="text" class="form-control" id="sub_notes" name="notas" maxlength="255"
                               placeholder="<?= e(__('reports.subscriptions.form.notes_placeholder')) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-check">
                            <input type="checkbox" class="form-check-input" name="monto_variable" value="1">
                            <span class="form-check-label small"><?= e(__('reports.subscriptions.variable')) ?></span>
                        </label>
                        <p class="small text-secondary mb-0"><?= e(__('reports.subscriptions.form.hint')) ?></p>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 mt-3"><?= e(__('common.actions.save')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</div>
