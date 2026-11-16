<?php

$portfolios = $portfolios ?? [];
$totals = $portfolio_totals ?? [];
$positions = $positions ?? [];
$instrumentLabels = $instrument_labels ?? [];
$portfolioNames = $portfolio_names ?? [];
$positionNames = $position_names ?? [];
$currency = (string) ($currency ?? 'MXN');
$timezone = new DateTimeZone((string) ($currentUser['zona_horaria'] ?? config('app.timezone', 'UTC')));
$today = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
$localDate = static function (string $value) use ($timezone): string {
    return (new DateTimeImmutable($value, new DateTimeZone('UTC')))->setTimezone($timezone)->format('Y-m-d');
};
$contributions = $contributions ?? [];
$withdrawals = $withdrawals ?? [];
$yields = $yields ?? [];
$consecutive = $consecutive ?? [];
$realPortfolios = array_values(array_filter($portfolios, static fn (array $p): bool => (int) $p['id'] > 0));

// «Portafolio · Instrumento» para el historial; los movimientos antiguos sin
// portafolio muestran su tipo de inversión.
$movementLabel = static function (array $row) use ($portfolioNames, $positionNames, $instrumentLabels): string {
    $portfolio = $portfolioNames[(int) ($row['portafolio_id'] ?? 0)] ?? null;

    if ($portfolio === null) {
        $key = (string) ($row['instrumento'] ?? 'otro');

        return (string) ($instrumentLabels[$key] ?? __('investment.instruments.' . $key));
    }

    $position = $positionNames[(int) ($row['posicion_id'] ?? 0)] ?? null;

    return $position !== null ? $portfolio . ' · ' . $position : $portfolio;
};
$classIcon = static fn (string $class): string => match ($class) {
    'sofipo' => 'bank',
    'acciones' => 'graph-up-arrow',
    'fibras' => 'buildings',
    'cetes_bonos' => 'file-earmark-text',
    'fondos' => 'pie-chart',
    'cripto' => 'currency-bitcoin',
    default => 'briefcase',
};
$signed = static fn (int $cents): string => ($cents > 0 ? '+' : '') . format_money($cents, $currency);
$gain = (int) ($totals['gain'] ?? 0);
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('investment.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('investment.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('investment.subtitle')) ?></p>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <?= partial('partials/lesson', ['lesson' => lesson_for('inversion')]) ?>
        <?php if ($realPortfolios !== []): ?>
            <button type="button" class="btn btn-primary" data-invest-prefill
                    data-bs-toggle="offcanvas" data-bs-target="#investmentContribute">
                <?= e(__('investment.actions.contribute')) ?>
            </button>
            <button type="button" class="btn btn-outline-secondary" data-invest-prefill
                    data-bs-toggle="offcanvas" data-bs-target="#investmentWithdraw">
                <?= e(__('investment.actions.withdraw')) ?>
            </button>
            <button type="button" class="btn btn-outline-secondary" data-invest-prefill
                    data-bs-toggle="offcanvas" data-bs-target="#investmentYield">
                <?= e(__('investment.yields.record')) ?>
            </button>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-secondary" data-portfolio-new
                data-bs-toggle="offcanvas" data-bs-target="#investmentPortfolio">
            <?= e(__('investment.portfolios.create')) ?>
        </button>
        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="offcanvas" data-bs-target="#investmentPause">
            <?= e(__('investment.actions.pause')) ?>
        </button>
    </div>
</div>

<?php if (!empty($paused)): ?>
    <div class="finance-warning mb-3" data-dismissible data-dismiss-key="investment-warning">
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

<div class="row g-3 mb-3">
    <div class="col-6 col-lg-3">
        <div class="finance-card finance-card-accent">
            <span class="finance-card-label"><?= e(__('investment.summary_v2.value')) ?></span>
            <span class="finance-card-value"><?= money_html((int) ($totals['value'] ?? 0), $currency) ?></span>
            <span class="small text-secondary"><?= e(__('investment.summary_v2.value_hint')) ?></span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('investment.summary_v2.invested')) ?></span>
            <span class="finance-card-value"><?= money_html((int) ($totals['invested'] ?? 0), $currency) ?></span>
            <span class="small text-secondary"><?= e(__('investment.summary_v2.invested_hint')) ?></span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('investment.summary_v2.gain')) ?></span>
            <span class="finance-card-value <?= $gain >= 0 ? 'text-success' : 'text-danger' ?>">
                <?= e($signed($gain)) ?>
            </span>
            <span class="small text-secondary">
                <?= e(number_format((float) ($totals['gain_pct'] ?? 0), 2, '.', ',')) ?>% ·
                <?= e(__('investment.summary_v2.gain_hint')) ?>
            </span>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('investment.summary_v2.yields_year')) ?></span>
            <span class="finance-card-value"><?= money_html((int) ($totals['yields_year'] ?? 0), $currency) ?></span>
            <span class="small text-secondary"><?= e(__('investment.summary_v2.yields_year_hint')) ?></span>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-md-6">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('finance.available')) ?></span>
            <span class="finance-card-value"><?= money_html((int) ($available_centavos ?? 0), $currency) ?></span>
            <span class="small text-secondary">
                <?= e(__('investment.summary.available_hint', [
                    ':assigned' => format_money((int) ($assigned_centavos ?? 0), $currency),
                    ':carry' => format_money((int) ($carry_centavos ?? 0), $currency),
                ])) ?>
            </span>
            <?php if (!empty($over_percentage)): ?>
                <span class="badge text-bg-warning mt-1 align-self-start"><?= e(__('finance.over_percentage')) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-12 col-md-6">
        <div class="finance-card">
            <span class="finance-card-label"><?= e(__('investment.summary.last')) ?></span>
            <?php if (!empty($last_contribution)): ?>
                <span class="finance-card-value">
                    <?= money_html((int) $last_contribution['monto_centavos'], (string) ($last_contribution['moneda'] ?? $currency)) ?>
                </span>
                <span class="small text-secondary">
                    <?= e($movementLabel($last_contribution)) ?>
                    · <?= e(format_date($last_contribution['fecha'] ?? null)) ?>
                </span>
            <?php else: ?>
                <span class="finance-card-value">—</span>
                <span class="small text-secondary"><?= e(__('investment.summary.no_last')) ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="surface-card mb-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="h5 mb-0"><?= e(__('investment.portfolios.title')) ?></h2>
            <p class="small text-secondary mb-0"><?= e(__('investment.portfolios.hint')) ?></p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-portfolio-new
                    data-bs-toggle="offcanvas" data-bs-target="#investmentPortfolio">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= e(__('investment.portfolios.create')) ?>
            </button>
            <?php if ($positions !== []): ?>
                <form method="post" action="/inversion/cotizaciones/actualizar" class="m-0">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-repeat" aria-hidden="true"></i> <?= e(__('investment.positions.refresh')) ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($portfolios === []): ?>
        <p class="small text-secondary mb-0"><?= e(__('investment.portfolios.empty')) ?></p>
    <?php else: ?>
        <div class="portfolio-list">
            <?php foreach ($portfolios as $index => $portfolio): ?>
                <?php
                $pid = (int) $portfolio['id'];
                $pGain = (int) $portfolio['gain'];
                $name = $pid > 0 ? (string) $portfolio['nombre'] : __('investment.portfolios.unassigned');
                ?>
                <details class="portfolio-card" <?= count($portfolios) === 1 ? 'open' : '' ?>>
                    <summary class="portfolio-summary">
                        <span class="portfolio-icon" aria-hidden="true">
                            <i class="bi bi-<?= e($classIcon((string) $portfolio['clase'])) ?>"></i>
                        </span>
                        <span class="portfolio-title">
                            <span class="fw-semibold d-block text-truncate"><?= e($name) ?></span>
                            <span class="small text-secondary d-block text-truncate">
                                <?= e(__('investment.classes.' . (string) $portfolio['clase'])) ?>
                                <?php if (!empty($portfolio['institucion'])): ?>
                                    · <?= e((string) $portfolio['institucion']) ?>
                                <?php endif; ?>
                                · <?= e(__('investment.portfolios.instruments_count', [':count' => (string) count($portfolio['instruments'])])) ?>
                            </span>
                        </span>
                        <span class="portfolio-figures">
                            <span class="fw-semibold d-block"><?= money_html((int) $portfolio['value'], $currency) ?></span>
                            <span class="small d-block <?= $pGain >= 0 ? 'text-success' : 'text-danger' ?>">
                                <?= e($signed($pGain)) ?>
                                (<?= e(number_format((float) $portfolio['gain_pct'], 2, '.', ',')) ?>%)
                            </span>
                        </span>
                        <i class="bi bi-chevron-down portfolio-chevron" aria-hidden="true"></i>
                    </summary>

                    <div class="portfolio-body">
                        <div class="row g-2 mb-3">
                            <div class="col-6 col-md-3">
                                <div class="report-metric">
                                    <span class="small text-secondary d-block"><?= e(__('investment.portfolios.invested')) ?></span>
                                    <span class="fw-semibold"><?= money_html((int) $portfolio['invested'], $currency) ?></span>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="report-metric">
                                    <span class="small text-secondary d-block"><?= e(__('investment.portfolios.cash')) ?></span>
                                    <span class="fw-semibold"><?= money_html((int) $portfolio['cash'], $currency) ?></span>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="report-metric">
                                    <span class="small text-secondary d-block"><?= e(__('investment.portfolios.gain')) ?></span>
                                    <span class="fw-semibold <?= $pGain >= 0 ? 'text-success' : 'text-danger' ?>"><?= e($signed($pGain)) ?></span>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <div class="report-metric">
                                    <span class="small text-secondary d-block"><?= e(__('investment.portfolios.yields_year')) ?></span>
                                    <span class="fw-semibold"><?= money_html((int) $portfolio['yields_year'], $currency) ?></span>
                                </div>
                            </div>
                        </div>

                        <?php if ($portfolio['instruments'] === []): ?>
                            <p class="small text-secondary"><?= e(__('investment.portfolios.empty_instruments')) ?></p>
                        <?php else: ?>
                            <div class="finance-list mb-3">
                                <?php foreach ($portfolio['instruments'] as $position): ?>
                                    <?php
                                    $posId = (int) $position['id'];
                                    $posGain = (int) $position['ganancia_centavos'];
                                    $mode = (string) $position['modo_valor'];
                                    ?>
                                    <div class="finance-row instrument-row">
                                        <?= partial('partials/cover', [
                                            'target' => 'posicion',
                                            'id' => $posId,
                                            'path' => $position['portada_path'] ?? null,
                                            'name' => (string) $position['nombre'],
                                            'icon' => 'graph-up-arrow',
                                            'small' => true,
                                        ]) ?>
                                        <div class="flex-grow-1 min-w-0">
                                            <span class="fw-semibold d-block text-truncate">
                                                <?= e((string) ($position['ticker'] ?: $position['nombre'])) ?>
                                                <?php if (!empty($position['ticker'])): ?>
                                                    <span class="small text-secondary fw-normal"><?= e((string) $position['nombre']) ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <span class="small text-secondary d-block">
                                                <?= e(__('investment.portfolio.types.' . (string) $position['tipo'])) ?>
                                                <?php if ($mode === 'automatico'): ?>
                                                    · <?= e(rtrim(rtrim(number_format((float) $position['cantidad'], 8, '.', ','), '0'), '.')) ?>
                                                <?php endif; ?>
                                                · <span class="badge text-bg-light border">
                                                    <?= e($mode === 'automatico' ? __('investment.portfolio.auto') : ($mode === 'calculado' ? __('investment.positions.mode_calc') : __('investment.portfolio.manual'))) ?>
                                                </span>
                                                <?php if (!empty($position['desactualizado'])): ?>
                                                    · <span class="badge text-bg-warning"><?= e(__('investment.portfolio.stale')) ?></span>
                                                <?php endif; ?>
                                                <?php if ($position['tasa_anual'] !== null && $position['tasa_anual'] !== ''): ?>
                                                    · <?= e(__('investment.portfolios.rate', [':rate' => rtrim(rtrim((string) $position['tasa_anual'], '0'), '.')])) ?>
                                                <?php endif; ?>
                                                <?php if (!empty($position['fecha_vencimiento'])): ?>
                                                    · <?= e(__('investment.portfolios.matures', [':date' => format_date($position['fecha_vencimiento'])])) ?>
                                                <?php endif; ?>
                                            </span>
                                            <span class="small text-secondary d-block">
                                                <?= e(__('investment.portfolio.cost_value', [
                                                    ':cost' => format_money((int) $position['costo_principal_centavos'], $currency),
                                                    ':value' => format_money((int) $position['valor_principal_centavos'], $currency),
                                                ])) ?>
                                            </span>
                                        </div>
                                        <div class="text-end instrument-side">
                                            <span class="fw-semibold d-block <?= $posGain >= 0 ? 'text-success' : 'text-danger' ?>">
                                                <?= e($signed($posGain)) ?>
                                            </span>
                                            <span class="small text-secondary d-block">
                                                <?= e(number_format((float) $position['ganancia_pct'], 2, '.', ',')) ?>%
                                            </span>
                                            <div class="dropdown mt-1">
                                                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                        data-bs-toggle="dropdown" aria-expanded="false"
                                                        aria-label="<?= e(__('investment.movements.actions')) ?>">
                                                    <i class="bi bi-three-dots" aria-hidden="true"></i>
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li>
                                                        <button type="button" class="dropdown-item" data-invest-prefill
                                                                data-portfolio="<?= $pid ?>" data-position="<?= $posId ?>"
                                                                data-bs-toggle="offcanvas" data-bs-target="#investmentContribute">
                                                            <i class="bi bi-plus-circle me-2" aria-hidden="true"></i><?= e(__('investment.actions.contribute')) ?>
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button type="button" class="dropdown-item" data-invest-prefill
                                                                data-portfolio="<?= $pid ?>" data-position="<?= $posId ?>"
                                                                data-bs-toggle="offcanvas" data-bs-target="#investmentWithdraw">
                                                            <i class="bi bi-dash-circle me-2" aria-hidden="true"></i><?= e(__('investment.actions.withdraw')) ?>
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button type="button" class="dropdown-item" data-invest-prefill
                                                                data-portfolio="<?= $pid ?>" data-position="<?= $posId ?>"
                                                                data-bs-toggle="offcanvas" data-bs-target="#investmentYield">
                                                            <i class="bi bi-percent me-2" aria-hidden="true"></i><?= e(__('investment.yields.record')) ?>
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button type="button" class="dropdown-item" data-position-value-edit
                                                                data-position-id="<?= $posId ?>"
                                                                data-position-name="<?= e((string) $position['nombre']) ?>"
                                                                data-position-value="<?= e(number_format((int) ($position['valor_principal_centavos'] ?? 0) / 100, 2, '.', '')) ?>"
                                                                data-bs-toggle="offcanvas" data-bs-target="#investmentPositionValue">
                                                            <i class="bi bi-currency-dollar me-2" aria-hidden="true"></i><?= e(__('investment.positions.update_value')) ?>
                                                        </button>
                                                    </li>
                                                    <li>
                                                        <button type="button" class="dropdown-item" data-position-edit
                                                                data-position-id="<?= $posId ?>"
                                                                data-position-portfolio="<?= $pid ?>"
                                                                data-position-type="<?= e((string) $position['tipo']) ?>"
                                                                data-position-market="<?= e((string) $position['mercado']) ?>"
                                                                data-position-ticker="<?= e((string) ($position['ticker'] ?? '')) ?>"
                                                                data-position-symbol="<?= e((string) ($position['simbolo_proveedor'] ?? '')) ?>"
                                                                data-position-name="<?= e((string) $position['nombre']) ?>"
                                                                data-position-currency="<?= e((string) $position['moneda']) ?>"
                                                                data-position-quantity="<?= e(number_format((float) $position['cantidad'], 8, '.', '')) ?>"
                                                                data-position-cost="<?= e(number_format((int) $position['costo_total_centavos'] / 100, 2, '.', '')) ?>"
                                                                data-position-mode="<?= e($mode) ?>"
                                                                data-position-value="<?= e(number_format((int) ($position['valor_manual_centavos'] ?? 0) / 100, 2, '.', '')) ?>"
                                                                data-position-date="<?= e(substr((string) ($position['fecha_valor_manual'] ?? ''), 0, 10)) ?>"
                                                                data-position-rate="<?= e($position['tasa_anual'] !== null ? rtrim(rtrim((string) $position['tasa_anual'], '0'), '.') : '') ?>"
                                                                data-position-maturity="<?= e((string) ($position['fecha_vencimiento'] ?? '')) ?>"
                                                                data-position-notes="<?= e((string) ($position['notas'] ?? '')) ?>"
                                                                data-bs-toggle="offcanvas" data-bs-target="#investmentPosition">
                                                            <i class="bi bi-pencil me-2" aria-hidden="true"></i><?= e(__('common.actions.edit')) ?>
                                                        </button>
                                                    </li>
                                                    <?php if (count($realPortfolios) > 1): ?>
                                                        <li>
                                                            <button type="button" class="dropdown-item" data-position-move
                                                                    data-position-id="<?= $posId ?>" data-portfolio="<?= $pid ?>"
                                                                    data-position-name="<?= e((string) $position['nombre']) ?>"
                                                                    data-bs-toggle="offcanvas" data-bs-target="#investmentMove">
                                                                <i class="bi bi-arrow-left-right me-2" aria-hidden="true"></i><?= e(__('investment.portfolios.move')) ?>
                                                            </button>
                                                        </li>
                                                    <?php endif; ?>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li>
                                                        <form method="post" action="/inversion/posiciones/eliminar" class="m-0"
                                                              data-confirm="<?= e(__('finance.confirm_delete')) ?>" data-confirm-danger>
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="id" value="<?= $posId ?>">
                                                            <button type="submit" class="dropdown-item text-danger">
                                                                <i class="bi bi-trash me-2" aria-hidden="true"></i><?= e(__('common.actions.delete')) ?>
                                                            </button>
                                                        </form>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($pid > 0): ?>
                            <div class="d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-primary" data-invest-prefill data-portfolio="<?= $pid ?>"
                                        data-bs-toggle="offcanvas" data-bs-target="#investmentContribute">
                                    <?= e(__('investment.actions.contribute')) ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-position-new data-portfolio="<?= $pid ?>"
                                        data-portfolio-class="<?= e((string) $portfolio['clase']) ?>"
                                        data-bs-toggle="offcanvas" data-bs-target="#investmentPosition">
                                    <?= e(__('investment.portfolios.add_instrument')) ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-invest-prefill data-portfolio="<?= $pid ?>"
                                        data-bs-toggle="offcanvas" data-bs-target="#investmentYield">
                                    <?= e(__('investment.yields.record')) ?>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-portfolio-edit
                                        data-id="<?= $pid ?>"
                                        data-name="<?= e((string) $portfolio['nombre']) ?>"
                                        data-class="<?= e((string) $portfolio['clase']) ?>"
                                        data-institution="<?= e((string) ($portfolio['institucion'] ?? '')) ?>"
                                        data-bs-toggle="offcanvas" data-bs-target="#investmentPortfolio"
                                        aria-label="<?= e(__('investment.portfolios.edit')) ?>">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                </button>
                                <form method="post" action="/inversion/portafolios/eliminar" class="m-0"
                                      data-confirm="<?= e(__('investment.portfolios.delete_confirm')) ?>" data-confirm-danger>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $pid ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="<?= e(__('common.actions.delete')) ?>">
                                        <i class="bi bi-trash" aria-hidden="true"></i>
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                </details>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
    <h2 class="h5 mb-0"><?= e(__('investment.movements.history')) ?></h2>
    <?php if (count($realPortfolios) > 1): ?>
        <select class="form-select form-select-sm w-auto" data-history-filter aria-label="<?= e(__('investment.movements.filter')) ?>">
            <option value=""><?= e(__('investment.movements.all_portfolios')) ?></option>
            <?php foreach ($realPortfolios as $portfolio): ?>
                <option value="<?= (int) $portfolio['id'] ?>"><?= e((string) $portfolio['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
    <?php endif; ?>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-4">
        <div class="surface-card h-100">
            <h3 class="h6 mb-3"><?= e(__('investment.movements.contributions')) ?></h3>
            <?php if ($contributions === []): ?>
                <p class="text-secondary small mb-0"><?= e(__('investment.movements.empty')) ?></p>
            <?php else: ?>
                <div class="finance-list">
                    <?php foreach ($contributions as $row): ?>
                        <div class="finance-row" data-history-portfolio="<?= (int) ($row['portafolio_id'] ?? 0) ?>">
                            <div class="flex-grow-1 min-w-0">
                                <span class="fw-semibold d-block"><?= money_html((int) $row['monto_centavos'], (string) ($row['moneda'] ?? $currency)) ?></span>
                                <span class="small text-secondary">
                                    <?= e($movementLabel($row)) ?>
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
                                        data-bs-toggle="offcanvas" data-bs-target="#investmentEditContribution"
                                        aria-label="<?= e(__('common.actions.edit')) ?>">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                </button>
                                <form method="post" action="/inversion/aportaciones/eliminar" class="m-0"
                                      data-confirm="<?= e(__('finance.confirm_delete')) ?>" data-confirm-danger>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="<?= e(__('common.actions.delete')) ?>">
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

    <div class="col-12 col-xl-4">
        <div class="surface-card h-100">
            <h3 class="h6 mb-3"><?= e(__('investment.movements.withdrawals')) ?></h3>
            <?php if ($withdrawals === []): ?>
                <p class="text-secondary small mb-0"><?= e(__('investment.movements.empty')) ?></p>
            <?php else: ?>
                <div class="finance-list">
                    <?php foreach ($withdrawals as $row): ?>
                        <div class="finance-row" data-history-portfolio="<?= (int) ($row['portafolio_id'] ?? 0) ?>">
                            <div class="flex-grow-1 min-w-0">
                                <span class="fw-semibold d-block"><?= money_html((int) $row['monto_centavos'], (string) ($row['moneda'] ?? $currency)) ?></span>
                                <span class="small text-secondary">
                                    <?= e($movementLabel($row)) ?>
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
                                        data-bs-toggle="offcanvas" data-bs-target="#investmentEditWithdrawal"
                                        aria-label="<?= e(__('common.actions.edit')) ?>">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                </button>
                                <form method="post" action="/inversion/retiros/eliminar" class="m-0"
                                      data-confirm="<?= e(__('finance.confirm_delete')) ?>" data-confirm-danger>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="<?= e(__('common.actions.delete')) ?>">
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

    <div class="col-12 col-xl-4">
        <div class="surface-card h-100">
            <h3 class="h6 mb-3"><?= e(__('investment.yields.title')) ?></h3>
            <?php if ($yields === []): ?>
                <p class="text-secondary small mb-0"><?= e(__('investment.yields.empty')) ?></p>
            <?php else: ?>
                <div class="finance-list">
                    <?php foreach ($yields as $row): ?>
                        <?php
                        $type = (string) $row['tipo'];
                        $loss = in_array($type, ['baja', 'comision'], true);
                        ?>
                        <div class="finance-row" data-history-portfolio="<?= (int) $row['portafolio_id'] ?>">
                            <div class="flex-grow-1 min-w-0">
                                <span class="fw-semibold d-block <?= $loss ? 'text-danger' : 'text-success' ?>">
                                    <?= $loss ? '−' : '+' ?><?= money_html((int) $row['monto_centavos'], (string) $row['moneda']) ?>
                                </span>
                                <span class="small text-secondary">
                                    <?= e(__('investment.yields.types.' . $type)) ?>
                                    · <?= e($movementLabel($row)) ?>
                                    <?php if (!$loss): ?>
                                        · <?= e(__('investment.yields.destinations.' . (string) $row['destino'])) ?>
                                    <?php endif; ?>
                                    · <?= e(format_date($row['fecha'] ?? null)) ?>
                                    <?php if (!empty($row['nota'])): ?>
                                        · <?= e((string) $row['nota']) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                            <?php if (!empty($row['editable'])): ?>
                                <button type="button" class="btn btn-sm btn-outline-secondary" data-yield-edit
                                        data-id="<?= (int) $row['id'] ?>"
                                        data-type="<?= e($type) ?>"
                                        data-amount="<?= e(number_format((int) $row['monto_centavos'] / 100, 2, '.', '')) ?>"
                                        data-currency="<?= e((string) $row['moneda']) ?>"
                                        data-date="<?= e($localDate((string) $row['fecha'])) ?>"
                                        data-note="<?= e((string) ($row['nota'] ?? '')) ?>"
                                        data-paid="<?= (string) $row['destino'] === 'pagado' && !$loss ? '1' : '0' ?>"
                                        data-bs-toggle="offcanvas" data-bs-target="#investmentEditYield"
                                        aria-label="<?= e(__('common.actions.edit')) ?>">
                                    <i class="bi bi-pencil" aria-hidden="true"></i>
                                </button>
                                <form method="post" action="/inversion/rendimientos/eliminar" class="m-0"
                                      data-confirm="<?= e(__('finance.confirm_delete')) ?>" data-confirm-danger>
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="<?= e(__('common.actions.delete')) ?>">
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
    'portfolios' => $realPortfolios,
    'withdrawReasons' => $withdraw_reasons ?? [],
    'currency' => $currency,
    'today' => $today,
]) ?>
