<?php


$byInstrument = $by_instrument ?? [];
$positions = $positions ?? [];
$portfolioValue = (int) ($portfolio_value_centavos ?? 0);
$portfolioMarket = (int) ($portfolio_market_centavos ?? 0);
$portfolioCost = (int) ($portfolio_cost_centavos ?? 0);
$portfolioUnassigned = (int) ($portfolio_unassigned_centavos ?? 0);
$portfolioDifference = (int) ($portfolio_difference_centavos ?? 0);
$instruments = $instruments ?? [];
$instrumentLabels = $instrument_labels ?? [];
$instrumentName = static fn (string $key): string => (string) ($instrumentLabels[$key] ?? __('investment.instruments.' . $key));
$currency = (string) ($currency ?? 'MXN');
$timezone = new DateTimeZone((string) ($currentUser['zona_horaria'] ?? config('app.timezone', 'UTC')));
$today = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
$localDate = static function (string $value) use ($timezone): string {
    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone($timezone)->format('Y-m-d');
};
$contributions = $contributions ?? [];
$withdrawals = $withdrawals ?? [];
$consecutive = $consecutive ?? [];
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('investment.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('investment.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('investment.subtitle')) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?= partial('partials/lesson', ['lesson' => lesson_for('inversion')]) ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="offcanvas" data-bs-target="#investmentContribute">
            <?= e(__('investment.actions.contribute')) ?>
        </button>
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="offcanvas" data-bs-target="#investmentWithdraw">
            <?= e(__('investment.actions.withdraw')) ?>
        </button>
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="offcanvas" data-bs-target="#investmentPosition">
            <?= e(__('investment.positions.create')) ?>
        </button>
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="offcanvas" data-bs-target="#investmentPause">
            <?= e(__('investment.actions.pause')) ?>
        </button>
    </div>
</div>

<?php if (!empty($paused)): ?>
    <div class="finance-warning mb-3">
        <i class="bi bi-pause-circle" aria-hidden="true"></i>
        <div class="flex-grow-1">
            <strong class="d-block"><?= e(__('investment.paused_title')) ?></strong>
            <span class="small">
                <?= e(__('investment.paused_body', [
                    ':amount' => format_money((int) ($pause['monto_centavos'] ?? 0), $currency),
                ])) ?>
            </span>
        </div>
    </div>
<?php endif; ?>

<?php if (!empty($consecutive['consecutive'])): ?>
    <div class="finance-warning mb-3" data-finance-warning>
        <i class="bi bi-exclamation-triangle" aria-hidden="true"></i>
        <div class="flex-grow-1">
            <strong class="d-block"><?= e(__('investment.consecutive_title')) ?></strong>
            <span class="small"><?= e(__('investment.consecutive_body')) ?></span>
        </div>
        <?php if (!empty($can_adjust)): ?>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="offcanvas"
                    data-bs-target="#investmentAdjust">
                <?= e(__('investment.consecutive_adjust')) ?>
            </button>
        <?php else: ?>
            <span class="small text-secondary"><?= e(__('investment.consecutive_no_adjust')) ?></span>
        <?php endif; ?>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-dismiss-warning>
            <?= e(__('investment.consecutive_continue')) ?>
        </button>
    </div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('investment.summary.total')) ?></span>
            <span class="finance-card-value"><?= money_html((int) ($total_centavos ?? 0), $currency) ?></span>
            <span class="small text-secondary"><?= e(__('investment.summary.total_hint')) ?></span>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="finance-card finance-card-accent">
            <span class="finance-card-label"><?= e(__('finance.available')) ?></span>
            <span class="finance-card-value"><?= money_html((int) ($available_centavos ?? 0), $currency) ?></span>
            <span class="small text-secondary">
                <?= e(__('investment.summary.available_hint', [
                    ':assigned' => format_money((int) ($assigned_centavos ?? 0), $currency),
                    ':carry' => format_money((int) ($carry_centavos ?? 0), $currency),
                ])) ?>
            </span>
            <?php if (!empty($over_percentage)): ?>
                <span class="badge text-bg-warning mt-1"><?= e(__('finance.over_percentage')) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('investment.summary.last')) ?></span>
            <?php if (!empty($last_contribution)): ?>
                <span class="finance-card-value">
                    <?= money_html((int) $last_contribution['monto_centavos'], $currency) ?>
                </span>
                <span class="small text-secondary">
                    <?= e($instrumentName((string) $last_contribution['instrumento'])) ?>
                    · <?= e(format_date($last_contribution['fecha'] ?? null)) ?>
                </span>
            <?php else: ?>
                <span class="finance-card-value">—</span>
                <span class="small text-secondary"><?= e(__('investment.summary.no_last')) ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-4">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('investment.portfolio.value')) ?></span>
            <span class="finance-card-value"><?= money_html($portfolioValue, $currency) ?></span>
            <span class="small text-secondary"><?= e(__('investment.portfolio.value_hint')) ?></span>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('investment.portfolio.cost')) ?></span>
            <span class="finance-card-value"><?= money_html($portfolioCost, $currency) ?></span>
            <span class="small text-secondary"><?= e(__('investment.portfolio.cost_hint')) ?></span>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="finance-card <?= $portfolioDifference >= 0 ? 'finance-card-accent' : '' ?>">
            <span class="finance-card-label"><?= e(__('investment.portfolio.difference')) ?></span>
            <span class="finance-card-value"><?= money_html($portfolioDifference, $currency) ?></span>
            <span class="small text-secondary">
                <?= e(__('investment.portfolio.unassigned', [
                    ':amount' => format_money($portfolioUnassigned, $currency),
                ])) ?>
            </span>
        </div>
    </div>
</div>

<div class="surface-card mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h5 mb-0"><?= e(__('investment.portfolio.title')) ?></h2>
            <p class="small text-secondary mb-0"><?= e(__('investment.portfolio.hint')) ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-position-new
                    data-bs-toggle="offcanvas" data-bs-target="#investmentPosition">
                <?= e(__('investment.positions.create')) ?>
            </button>
            <form method="post" action="/inversion/cotizaciones/actualizar" class="m-0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <?= e(__('investment.positions.refresh')) ?>
                </button>
            </form>
        </div>
    </div>

    <?php if ($positions === []): ?>
        <p class="small text-secondary mb-0"><?= e(__('investment.portfolio.empty')) ?></p>
    <?php else: ?>
        <div class="finance-list">
            <?php foreach ($positions as $position): ?>
                <div class="finance-row align-items-start gap-2">
                    <?= partial('partials/cover', [
                        'target' => 'posicion',
                        'id' => (int) $position['id'],
                        'path' => $position['portada_path'] ?? null,
                        'name' => (string) $position['nombre'],
                        'icon' => 'graph-up-arrow',
                        'small' => true,
                    ]) ?>
                    <div class="flex-grow-1">
                        <span class="fw-semibold d-block">
                            <?= e((string) ($position['ticker'] ?: $position['nombre'])) ?>
                            <span class="small text-secondary fw-normal"><?= e((string) $position['nombre']) ?></span>
                        </span>
                        <span class="small text-secondary d-block">
                            <?= e(__('investment.portfolio.types.' . (string) $position['tipo'])) ?>
                            · <?= e((string) $position['mercado']) ?>
                            · <?= e(number_format((float) $position['cantidad'], 8, '.', ',')) ?>
                            <?php if ((string) $position['modo_valor'] === 'manual'): ?>
                                · <span class="badge text-bg-light border"><?= e(__('investment.portfolio.manual')) ?></span>
                            <?php else: ?>
                                · <span class="badge text-bg-light border"><?= e(__('investment.portfolio.auto')) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($position['desactualizado'])): ?>
                                · <span class="badge text-bg-warning"><?= e(__('investment.portfolio.stale')) ?></span>
                            <?php endif; ?>
                        </span>
                        <span class="small text-secondary d-block">
                            <?= e(__('investment.portfolio.cost_value', [
                                ':cost' => format_money((int) $position['costo_principal_centavos'], $currency),
                                ':value' => format_money((int) $position['valor_principal_centavos'], $currency),
                            ])) ?>
                            <?php if (!empty($position['precio_fecha'])): ?>
                                · <?= e(__('investment.portfolio.updated', [':date' => format_date($position['precio_fecha'])])) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="text-end">
                        <span class="fw-semibold d-block <?= (int) $position['diferencia_principal_centavos'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= money_html((int) $position['diferencia_principal_centavos'], $currency) ?>
                        </span>
                        <span class="small text-secondary d-block">
                            <?= e(number_format((float) $position['diferencia_pct'], 2, '.', ',')) ?>%
                        </span>
                        <div class="d-flex gap-1 mt-2 justify-content-end">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-position-edit
                                    data-position-id="<?= (int) $position['id'] ?>"
                                    data-position-type="<?= e((string) $position['tipo']) ?>"
                                    data-position-market="<?= e((string) $position['mercado']) ?>"
                                    data-position-ticker="<?= e((string) ($position['ticker'] ?? '')) ?>"
                                    data-position-symbol="<?= e((string) ($position['simbolo_proveedor'] ?? '')) ?>"
                                    data-position-name="<?= e((string) $position['nombre']) ?>"
                                    data-position-currency="<?= e((string) $position['moneda']) ?>"
                                    data-position-quantity="<?= e(number_format((float) $position['cantidad'], 8, '.', '')) ?>"
                                    data-position-cost="<?= e(number_format((int) $position['costo_total_centavos'] / 100, 2, '.', '')) ?>"
                                    data-position-mode="<?= e((string) $position['modo_valor']) ?>"
                                    data-position-value="<?= e(number_format((int) ($position['valor_manual_centavos'] ?? 0) / 100, 2, '.', '')) ?>"
                                    data-position-date="<?= e(substr((string) ($position['fecha_valor_manual'] ?? ''), 0, 10)) ?>"
                                    data-bs-toggle="offcanvas" data-bs-target="#investmentPosition">
                                <i class="bi bi-pencil" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-position-value-edit
                                    data-position-id="<?= (int) $position['id'] ?>"
                                    data-position-name="<?= e((string) $position['nombre']) ?>"
                                    data-position-value="<?= e(number_format((int) ($position['valor_nativo_centavos'] ?? 0) / 100, 2, '.', '')) ?>"
                                    data-bs-toggle="offcanvas" data-bs-target="#investmentPositionValue">
                                <i class="bi bi-currency-dollar" aria-hidden="true"></i>
                            </button>
                            <form method="post" action="/inversion/posiciones/eliminar" class="m-0"
                                  onsubmit="return confirm('<?= e(__('finance.confirm_delete')) ?>')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $position['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash" aria-hidden="true"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="row g-3 mb-4">
    <?php foreach ($instruments as $instrument): ?>
        <div class="col-6 col-lg-4 col-xl-2">
            <div class="instrument-card">
                <span class="instrument-name"><?= e($instrumentName((string) $instrument)) ?></span>
                <span class="instrument-value">
                    <?= money_html((int) ($byInstrument[$instrument] ?? 0), $currency) ?>
                </span>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-6">
        <div class="surface-card">
            <h2 class="h5 mb-3"><?= e(__('investment.movements.contributions')) ?></h2>
            <?php if ($contributions === []): ?>
                <p class="text-secondary small mb-0"><?= e(__('investment.movements.empty')) ?></p>
            <?php else: ?>
                <div class="finance-list">
                    <?php foreach ($contributions as $row): ?>
                        <div class="finance-row">
                            <div class="flex-grow-1">
                                <span class="fw-semibold d-block"><?= money_html((int) $row['monto_centavos'], $currency) ?></span>
                                <span class="small text-secondary">
                                    <?= e($instrumentName((string) $row['instrumento'])) ?>
                                    · <?= e(format_date($row['fecha'] ?? null)) ?>
                                    <?php if ((int) $row['es_extra'] === 1): ?>
                                        · <?= e(__('investment.extra_short')) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if (!empty($row['editable'])): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-investment-contribution-edit
                                        data-id="<?= (int) $row['id'] ?>"
                                        data-amount="<?= e(number_format((int) $row['monto_centavos'] / 100, 2, '.', '')) ?>"
                                        data-date="<?= e($localDate((string) $row['fecha'])) ?>"
                                        data-extra="<?= (int) $row['es_extra'] ?>"
                                        data-bs-toggle="offcanvas" data-bs-target="#investmentEditContribution">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                </button>
                                <form method="post" action="/inversion/aportaciones/eliminar" class="m-0"
                                      onsubmit="return confirm('<?= e(__('finance.confirm_delete')) ?>')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-12 col-xl-6">
        <div class="surface-card">
            <h2 class="h5 mb-3"><?= e(__('investment.movements.withdrawals')) ?></h2>
            <?php if ($withdrawals === []): ?>
                <p class="text-secondary small mb-0"><?= e(__('investment.movements.empty')) ?></p>
            <?php else: ?>
                <div class="finance-list">
                    <?php foreach ($withdrawals as $row): ?>
                        <div class="finance-row">
                            <div class="flex-grow-1">
                                <span class="fw-semibold d-block"><?= money_html((int) $row['monto_centavos'], $currency) ?></span>
                                <span class="small text-secondary">
                                    <?= e($instrumentName((string) $row['instrumento'])) ?>
                                    · <?= e(__('investment.reasons.' . (string) $row['motivo_catalogo'])) ?>
                                    · <?= e(format_date($row['fecha'] ?? null)) ?>
                                </span>
                            </div>
                            <?php if (!empty($row['editable'])): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-investment-withdrawal-edit
                                        data-id="<?= (int) $row['id'] ?>"
                                        data-amount="<?= e(number_format((int) $row['monto_centavos'] / 100, 2, '.', '')) ?>"
                                        data-date="<?= e($localDate((string) $row['fecha'])) ?>"
                                        data-reason="<?= e((string) $row['motivo_catalogo']) ?>"
                                        data-text="<?= e((string) ($row['motivo_texto'] ?? '')) ?>"
                                        data-bs-toggle="offcanvas" data-bs-target="#investmentEditWithdrawal">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                </button>
                                <form method="post" action="/inversion/retiros/eliminar" class="m-0"
                                      onsubmit="return confirm('<?= e(__('finance.confirm_delete')) ?>')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
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

<?= partial('investment/sheets', [
    'instruments' => $instruments,
    'instrumentLabels' => $instrumentLabels,
    'withdrawReasons' => $withdraw_reasons ?? [],
    'currency' => $currency,
    'today' => $today,
]) ?>
