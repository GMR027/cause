<?php

$alias = (string) ($currentUser['alias'] ?? '');
$currency = (string) ($currentUser['moneda_activa'] ?? 'MXN');
$methodName = $methodName ?? null;
$percentages = $percentages ?? [];
$methodAmounts = $methodAmounts ?? [];
$methodStatus = $methodStatus ?? [];
$methodBase = (int) ($methodBase ?? 0);
$methodBaseSource = (string) ($methodBaseSource ?? 'period');
$period = $period ?? null;
$noControladoTotal = (int) ($noControladoTotal ?? 0);
$pendingIncome = $pendingIncome ?? null;
$pendingGastos = $pendingGastos ?? [];
$recurringConfirm = $recurringConfirm ?? [];
$insights = $insights ?? [];
$pendingGastosTotal = array_sum(array_map(
    static fn (array $row): int => (int) ($row['monto_principal_centavos'] ?? $row['monto_centavos']),
    $pendingGastos
));
$incomeTotal = (int) ($incomeTotal ?? 0);
$expenseTotal = (int) ($expenseTotal ?? 0);
$availableTotal = (int) ($availableTotal ?? 0);
$finance = $finance ?? [];
$projection = $projection ?? [];
$score = $score ?? [];
$format = static fn (int $cents): string => money_html($cents, $currency);
?>
<section class="page-heading">
    <div>
        <p class="text-secondary mb-1"><?= e($greeting ?? '') ?></p>
        <h1 class="h3 mb-1"><?= e(__('app.home.welcome', [':alias' => $alias])) ?></h1>
        <p class="text-secondary mb-0">
            <?= e(format_date($now ?? null)) ?>
            <?php if ($period !== null): ?>
                · <?= e(__('income.period_label')) ?>
                <?= e(format_date($period['inicio'] ?? null)) ?> – <?= e(format_date($period['fin'] ?? null)) ?>
            <?php endif; ?>
        </p>
    </div>
    <a href="/ingresos/nuevo" class="btn btn-primary d-none d-xl-inline-flex">
        <i class="bi bi-plus-lg me-1" aria-hidden="true"></i><?= e(__('nav.fab.ingreso')) ?>
    </a>
</section>

<?php if ($pendingIncome !== null): ?>
    <div class="pending-banner" role="status" data-dismissible data-dismiss-key="pending-income-<?= (int) $pendingIncome['id'] ?>">
        <span class="pending-icon" aria-hidden="true"><i class="bi bi-exclamation-circle"></i></span>
        <div class="flex-grow-1">
            <strong class="d-block"><?= e(__('income.pending_banner')) ?></strong>
            <span class="small text-secondary">
                <?= e(__('income.pending_detail', [
                    ':date' => format_date($pendingIncome['fecha'] ?? null),
                ])) ?>
                · <?= money_html((int) $pendingIncome['monto_centavos'], (string) ($pendingIncome['moneda'] ?? $currency)) ?>
            </span>
        </div>
        <a class="btn btn-sm btn-warning"
           href="/ingresos/acreditar?id=<?= (int) $pendingIncome['id'] ?>">
            <?= e(__('income.pending_cta')) ?>
        </a>
    </div>
<?php endif; ?>

<?php if ($pendingGastos !== []): ?>
    <div class="pending-banner pending-banner-gastos" role="status" data-dismissible
         data-dismiss-key="pending-gastos-<?= count($pendingGastos) ?>-<?= (int) $pendingGastosTotal ?>">
        <span class="pending-icon" aria-hidden="true"><i class="bi bi-receipt"></i></span>
        <div class="flex-grow-1">
            <strong class="d-block">
                <?= e(__('expenses.pending.count', [':count' => (string) count($pendingGastos)])) ?>
            </strong>
            <span class="small text-secondary">
                <?= e(__('expenses.pending.total')) ?>: <?= money_html($pendingGastosTotal, $currency) ?>
            </span>
        </div>
        <a class="btn btn-sm btn-warning" href="/gastos/conciliar">
            <?= e(__('expenses.pending.cta')) ?>
        </a>
    </div>
<?php endif; ?>

<?php foreach (array_slice($recurringConfirm, 0, 3) as $recurring): ?>
    <div class="recurring-confirm" role="status" data-dismissible data-dismiss-key="recurring-<?= (int) $recurring['id'] ?>">
        <div class="flex-grow-1">
            <strong class="d-block">
                <?= e(__('expenses.recurring.banner_title', [
                    ':name' => (string) ($recurring['recurrente_nombre'] ?? $recurring['parte_metodo']),
                ])) ?>
            </strong>
            <span class="small text-secondary">
                <?= money_html((int) $recurring['monto_centavos'], (string) $recurring['moneda']) ?>
                · <?= e(__('expenses.recurring.banner_hint')) ?>
            </span>
        </div>

        <form method="post" action="/gastos/confirmar" class="m-0">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $recurring['id'] ?>">
            <button type="submit" class="btn btn-sm btn-primary"><?= e(__('expenses.recurring.correct')) ?></button>
        </form>

        <a href="/gastos/editar?id=<?= (int) $recurring['id'] ?>" class="btn btn-sm btn-outline-secondary">
            <?= e(__('common.actions.edit')) ?>
        </a>

        <form method="post" action="/gastos/recurrente/detener" class="m-0">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $recurring['id'] ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary">
                <?= e(__('expenses.recurring.stop')) ?>
            </button>
        </form>
    </div>
<?php endforeach; ?>

<?php if ($insights !== []): ?>
    <div class="insight-card" data-dismiss-empty>
        <?php foreach ($insights as $insight): ?>
            <div class="insight-row" data-dismissible
                 data-dismiss-key="insight-<?= e(substr(md5((string) $insight['key'] . json_encode($insight['params'])), 0, 12)) ?>">
                <i class="bi <?= $insight['severity'] === 'warning' ? 'bi-exclamation-triangle' : 'bi-info-circle' ?>"
                   aria-hidden="true"></i>
                <div class="flex-grow-1">
                    <?= e(__((string) $insight['key'], (array) $insight['params'])) ?>
                    <?php if (!empty($insight['route']) && !empty($insight['action'])): ?>
                        <a href="<?= e((string) $insight['route']) ?>" class="ms-1">
                            <?= e(__((string) $insight['action'])) ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="metric-card">
            <span class="metric-label"><?= e(__('app.home.income')) ?></span>
            <span class="metric-value"><?= $format($incomeTotal) ?></span>
            <span class="metric-hint"><?= e(__('app.home.period_hint')) ?></span>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="metric-card">
            <span class="metric-label"><?= e(__('app.home.expenses')) ?></span>
            <span class="metric-value"><?= $format($expenseTotal) ?></span>
            <span class="metric-hint"><?= e(__('app.home.period_hint')) ?></span>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <?php if ($availableTotal < 0): ?>
            <a class="metric-card metric-card-over text-decoration-none" href="/movimientos">
                <span class="metric-label"><?= e(__('app.home.over')) ?></span>
                <span class="metric-value"><?= $format(abs($availableTotal)) ?></span>
                <span class="metric-hint"><?= e(__('app.home.over_hint')) ?></span>
            </a>
        <?php else: ?>
            <div class="metric-card metric-card-accent">
                <span class="metric-label"><?= e(__('app.home.available')) ?></span>
                <span class="metric-value"><?= $format($availableTotal) ?></span>
                <span class="metric-hint"><?= e(__('app.home.available_hint')) ?></span>
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="surface-card projection-card mb-4">
    <div class="d-flex flex-wrap align-items-start justify-content-between gap-2 mb-2">
        <div>
            <span class="small text-secondary d-block"><?= e(__('projection.home_title')) ?></span>
            <h2 class="h5 mb-0"><?= e(__('projection.title')) ?></h2>
        </div>
        <?php if (!empty($projection['has_data'])): ?>
            <span class="badge text-bg-light border">
                <?= e(__('projection.days', [
                    ':current' => (string) ($projection['days_elapsed'] ?? 0),
                    ':total' => (string) ($projection['days_total'] ?? 0),
                ])) ?>
            </span>
        <?php endif; ?>
    </div>

    <?php if (empty($projection['has_data'])): ?>
        <p class="small text-secondary mb-0"><?= e(__('projection.home_no_data')) ?></p>
    <?php else: ?>
        <p class="mb-2">
            <?php if ((int) ($projection['income_centavos'] ?? 0) > 0): ?>
                <?= __('projection.home_summary', [
                    ':spend' => money_html((int) ($projection['projected_spend_centavos'] ?? 0), (string) ($projection['currency'] ?? 'MXN')),
                    ':remaining' => money_html((int) ($projection['projected_remaining_centavos'] ?? 0), (string) ($projection['currency'] ?? 'MXN')),
                ]) ?>
            <?php else: ?>
                <?= __('projection.home_summary_no_income', [
                    ':spend' => money_html((int) ($projection['projected_spend_centavos'] ?? 0), (string) ($projection['currency'] ?? 'MXN')),
                ]) ?>
            <?php endif; ?>
        </p>

        <?php if (($projection['at_risk'] ?? []) !== []): ?>
            <ul class="small text-secondary mb-0">
                <?php foreach ($projection['at_risk'] as $risk): ?>
                    <li>
                        <?php if (!empty($risk['agotada'])): ?>
                            <?= e(__('projection.home_risk_depleted', [':category' => (string) $risk['nombre']])) ?>
                        <?php else: ?>
                            <?= e(__('projection.home_risk', [
                                ':category' => (string) $risk['nombre'],
                                ':date' => !empty($risk['agotamiento_fecha'])
                                    ? format_date($risk['agotamiento_fecha'] . ' 12:00:00')
                                    : '—',
                            ])) ?>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?= partial('partials/score-widget', ['score' => $score]) ?>

<?= partial('partials/progress-card', [
    'progress' => $progress ?? [],
    'currency' => (string) ($currentUser['moneda_activa'] ?? 'MXN'),
]) ?>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <a class="finance-tile" href="/ahorro">
            <span class="finance-tile-icon finance-tile-savings" aria-hidden="true">
                <i class="bi bi-coin finance-tile-coin"></i>
                <i class="bi bi-piggy-bank finance-tile-glyph"></i>
            </span>
            <span class="finance-tile-body">
                <span class="finance-tile-label"><?= e(__('savings.card.title')) ?></span>
                <span class="finance-tile-amount">
                    <?= money_html((int) ($finance['savings']['total_centavos'] ?? 0), $currency) ?>
                </span>
                <?php if ((int) ($finance['savings']['emergency_target_centavos'] ?? 0) > 0): ?>
                    <span class="finance-progress" aria-hidden="true">
                        <span style="width: <?= (int) ($finance['savings']['progress'] ?? 0) ?>%"></span>
                    </span>
                    <span class="small text-secondary">
                        <?= e(__('savings.card.emergency', [
                            ':percent' => (string) (int) ($finance['savings']['progress'] ?? 0),
                        ])) ?>
                    </span>
                <?php else: ?>
                    <span class="small text-secondary"><?= e(__('savings.card.hint')) ?></span>
                <?php endif; ?>
            </span>
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </a>
    </div>

    <div class="col-12 col-md-4">
        <a class="finance-tile" href="/inversion">
            <span class="finance-tile-icon finance-tile-investment" aria-hidden="true">
                <i class="bi bi-graph-up-arrow finance-tile-glyph"></i>
                <i class="bi bi-stars finance-tile-sparkle"></i>
            </span>
            <span class="finance-tile-body">
                <span class="finance-tile-label"><?= e(__('investment.card.title')) ?></span>
                <span class="finance-tile-amount">
                    <?= money_html((int) ($finance['investment']['total_centavos'] ?? 0), $currency) ?>
                </span>
                <span class="small text-secondary"><?= e(__('investment.card.hint')) ?></span>
            </span>
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </a>
    </div>

    <div class="col-12 col-md-4">
        <a class="finance-tile" href="/donacion">
            <span class="finance-tile-icon finance-tile-donation" aria-hidden="true">
                <i class="bi bi-heart-fill finance-tile-glyph"></i>
                <i class="bi bi-heart-fill finance-tile-float"></i>
                <i class="bi bi-heart-fill finance-tile-float finance-tile-float-2"></i>
            </span>
            <span class="finance-tile-body">
                <span class="finance-tile-label"><?= e(__('donation.card.title')) ?></span>
                <span class="finance-tile-amount">
                    <?= money_html((int) ($finance['donation']['period_centavos'] ?? 0), $currency) ?>
                </span>
                <?php if ((int) ($finance['donation']['assigned_centavos'] ?? 0) > 0): ?>
                    <span class="finance-progress" aria-hidden="true">
                        <span style="width: <?= (int) ($finance['donation']['progress'] ?? 0) ?>%"></span>
                    </span>
                    <span class="small text-secondary">
                        <?= e(__('donation.card.period', [
                            ':percent' => (string) (int) ($finance['donation']['progress'] ?? 0),
                        ])) ?>
                    </span>
                <?php else: ?>
                    <span class="small text-secondary"><?= e(__('donation.card.hint')) ?></span>
                <?php endif; ?>
            </span>
            <i class="bi bi-chevron-right" aria-hidden="true"></i>
        </a>
    </div>
</div>

<div class="row g-3">
    <div class="col-12 col-lg-7">
        <div class="surface-card h-100">
            <?php if ($expenseTotal > 0): ?>
                <h2 class="h5 mb-1"><?= e(__('app.home.movements_title')) ?></h2>
                <p class="text-secondary mb-3">
                    <?= e(__('app.home.movements_body')) ?>
                </p>
                <a href="/movimientos" class="btn btn-outline-secondary">
                    <?= e(__('app.home.view_movements')) ?>
                </a>
            <?php else: ?>
                <div class="empty-state">
                    <span class="empty-icon" aria-hidden="true"><i class="bi bi-inbox"></i></span>
                    <h2 class="h5 mb-1"><?= e(__('app.movimientos.empty_title')) ?></h2>
                    <p class="text-secondary mb-3"><?= e(__('app.movimientos.empty_body')) ?></p>
                    <a href="/gastos/nuevo" class="btn btn-primary">
                        <?= e(__('nav.fab.gasto')) ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-lg-5">
        <div class="surface-card h-100">
            <?php if ($methodName !== null): ?>
                <?php
                $methodKey = 'onboarding.methods.' . (string) ($methodCode ?? '') . '.name';
                $translatedMethod = __($methodKey);
                $displayMethod = $translatedMethod === $methodKey ? $methodName : $translatedMethod;
                ?>
                <span class="badge phase-badge mb-2"><?= e(__('app.home.method')) ?></span>
                <h2 class="h5 mb-3"><?= e($displayMethod) ?></h2>
                <ul class="method-list mb-0">
                    <?php foreach ($percentages as $name => $percentage): ?>
                        <?php
                        $status = $methodStatus[$name] ?? null;
                        $stateLabel = null;

                        if ($status !== null) {
                            $stateLabel = $status['estado'] === 'pendiente'
                                ? __('app.home.method_status.pendiente', [
                                    ':used' => format_money((int) $status['usado'], $currency),
                                    ':assigned' => format_money((int) $status['asignado'], $currency),
                                ])
                                : __('app.home.method_status.' . $status['estado']);
                        }
                        ?>
                        <li class="flex-wrap">
                            <span class="d-inline-flex align-items-center gap-2">
                                <?php if ($status !== null && $status['estado'] === 'cumplido'): ?>
                                    <i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i>
                                <?php elseif ($status !== null && $status['estado'] === 'excedido'): ?>
                                    <i class="bi bi-exclamation-circle-fill text-warning" aria-hidden="true"></i>
                                <?php elseif ($status !== null): ?>
                                    <i class="bi bi-circle text-secondary" aria-hidden="true"></i>
                                <?php endif; ?>
                                <?= e((string) $name) ?>
                            </span>
                            <span class="d-inline-flex align-items-baseline gap-2">
                                <?php if (isset($methodAmounts[$name])): ?>
                                    <span class="fw-semibold"><?= money_html((int) $methodAmounts[$name], $currency) ?></span>
                                <?php endif; ?>
                                <span class="text-secondary small"><?= (int) $percentage ?>%</span>
                            </span>
                            <?php if ($status !== null): ?>
                                <span class="w-100 d-block mt-1">
                                    <span class="progress d-flex" role="progressbar" style="height: 4px;"
                                          aria-valuenow="<?= (int) $status['progreso'] ?>" aria-valuemin="0" aria-valuemax="100"
                                          aria-label="<?= e(__('app.home.method_status.aria', [':name' => (string) $name, ':state' => (string) $stateLabel])) ?>">
                                        <span class="progress-bar <?= $status['estado'] === 'cumplido' ? 'bg-success' : ($status['estado'] === 'excedido' ? 'bg-warning' : '') ?>"
                                              style="width: <?= (int) $status['progreso'] ?>%"></span>
                                    </span>
                                    <span class="small <?= $status['estado'] === 'cumplido' ? 'text-success' : ($status['estado'] === 'excedido' ? 'text-warning-emphasis' : 'text-secondary') ?>">
                                        <?= e((string) $stateLabel) ?>
                                    </span>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ($methodAmounts !== []): ?>
                    <p class="small text-secondary mt-2 mb-0">
                        <?= __('app.home.method_' . ($methodBaseSource === 'estimated' ? 'estimated' : 'period') . '_hint', [
                            ':amount' => money_html($methodBase, $currency),
                        ]) ?>
                    </p>
                <?php endif; ?>
                <a href="/ajustes" class="btn btn-sm btn-outline-secondary mt-3 d-none d-xl-inline-flex">
                    <?= e(__('nav.ajustes')) ?>
                </a>
            <?php else: ?>
                <span class="badge phase-badge mb-2"><?= e(__('app.home.method')) ?></span>
                <h2 class="h5 mb-2"><?= e(__('app.home.no_method')) ?></h2>
                <p class="text-secondary mb-0"><?= e(__('app.home.no_method_hint')) ?></p>
            <?php endif; ?>

            <div class="no-controlado-card mt-3">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                    <span class="small fw-semibold text-uppercase"><?= e(__('income.no_controlado.title')) ?></span>
                    <i class="bi bi-exclamation-diamond" aria-hidden="true"></i>
                </div>
                <div class="fs-4 fw-bold"><?= money_html($noControladoTotal, $currency) ?></div>
                <p class="small mb-0"><?= e(__('income.no_controlado.hint')) ?></p>
            </div>
        </div>
    </div>
</div>
