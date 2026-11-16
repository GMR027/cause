<?php

$portfolios = $portfolios ?? [];
$withdrawReasons = $withdrawReasons ?? [];
$currency = (string) ($currency ?? 'MXN');
$today = (string) ($today ?? date('Y-m-d'));
$positionTypes = \Cauce\Services\InvestmentService::POSITION_TYPES;

/**
 * Selects ligados: portafolio y, filtrado con JS, su instrumento opcional.
 */
$targetFields = static function (string $prefix, string $portfolioName, string $positionName, bool $cashOption = true, bool $required = true) use ($portfolios): string {
    ob_start();
    ?>
    <div class="row g-2 mb-3">
        <div class="col-12 col-sm-6">
            <label class="form-label" for="<?= e($prefix) ?>_portfolio"><?= e(__('investment.positions.portfolio')) ?></label>
            <select class="form-select" id="<?= e($prefix) ?>_portfolio" name="<?= e($portfolioName) ?>"
                    data-portfolio-select="<?= e($prefix) ?>" <?= $required ? 'required' : '' ?>>
                <?php foreach ($portfolios as $portfolio): ?>
                    <option value="<?= (int) $portfolio['id'] ?>"><?= e((string) $portfolio['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-sm-6">
            <label class="form-label" for="<?= e($prefix) ?>_position"><?= e(__('investment.positions.title')) ?></label>
            <select class="form-select" id="<?= e($prefix) ?>_position" name="<?= e($positionName) ?>"
                    data-position-select="<?= e($prefix) ?>">
                <?php if ($cashOption): ?>
                    <option value="0"><?= e(__('investment.portfolios.cash_only')) ?></option>
                <?php endif; ?>
                <?php foreach ($portfolios as $portfolio): ?>
                    <?php foreach ($portfolio['instruments'] as $instrument): ?>
                        <option value="<?= (int) $instrument['id'] ?>" data-portfolio="<?= (int) $portfolio['id'] ?>">
                            <?= e((string) $instrument['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
};
?>
<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="investmentContribute"
     aria-labelledby="investmentContributeLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="investmentContributeLabel"><?= e(__('investment.actions.contribute')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/inversion/aportar">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label" for="investment_amount"><?= e(__('finance.amount')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="investment_amount" name="monto"
                           min="0.01" step="0.01" inputmode="decimal" required>
                </div>
            </div>

            <div class="mb-3">
                <?= partial('partials/currency-select', [
                    'name' => 'moneda',
                    'selected' => (is_string($_POST['moneda'] ?? null) ? $_POST['moneda'] : (string) ($currency ?? 'MXN')),
                ]) ?>
            </div>

            <?= $targetFields('contribute', 'portafolio_id', 'posicion_id') ?>

            <div class="mb-3">
                <label class="form-label" for="investment_date"><?= e(__('finance.date')) ?></label>
                <input type="date" class="form-control" id="investment_date" name="fecha" value="<?= e($today) ?>" required>
            </div>

            <label class="form-check mb-2">
                <input type="checkbox" class="form-check-input" name="es_extra" value="1">
                <span class="form-check-label"><?= e(__('investment.extra_label')) ?></span>
            </label>
            <p class="small text-secondary"><?= e(__('investment.extra_hint')) ?></p>

            <button type="submit" class="btn btn-primary w-100"><?= e(__('investment.actions.contribute')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="investmentWithdraw"
     aria-labelledby="investmentWithdrawLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="investmentWithdrawLabel"><?= e(__('investment.actions.withdraw')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/inversion/retirar" data-investment-withdraw-form>
            <?= csrf_field() ?>

            <?= $targetFields('withdraw', 'portafolio_id', 'posicion_id') ?>

            <div class="mb-3">
                <label class="form-label" for="investment_withdraw_amount"><?= e(__('finance.amount')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="investment_withdraw_amount" name="monto"
                           min="0.01" step="0.01" inputmode="decimal" required>
                </div>
            </div>

            <div class="mb-3">
                <?= partial('partials/currency-select', [
                    'name' => 'moneda',
                    'selected' => (is_string($_POST['moneda'] ?? null) ? $_POST['moneda'] : (string) ($currency ?? 'MXN')),
                ]) ?>
            </div>

            <div class="mb-3">
                <label class="form-label" for="investment_reason"><?= e(__('finance.reason')) ?></label>
                <select class="form-select" id="investment_reason" name="motivo" required
                        data-investment-withdraw-reason>
                    <?php foreach ($withdrawReasons as $reason): ?>
                        <option value="<?= e((string) $reason) ?>"><?= e(__('investment.reasons.' . (string) $reason)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div data-rebalance-target hidden>
                <p class="small fw-semibold mb-1"><?= e(__('investment.rebalance_target')) ?></p>
                <?= $targetFields('rebalance', 'destino_portafolio_id', 'destino_posicion_id', true, false) ?>
                <div class="form-text mt-n2 mb-3"><?= e(__('investment.rebalance_hint')) ?></div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="investment_reason_text"><?= e(__('finance.reason_text')) ?></label>
                <input type="text" class="form-control" id="investment_reason_text" name="motivo_texto" maxlength="255">
            </div>

            <div class="mb-3">
                <label class="form-label" for="investment_withdraw_date"><?= e(__('finance.date')) ?></label>
                <input type="date" class="form-control" id="investment_withdraw_date" name="fecha" value="<?= e($today) ?>" required>
            </div>

            <button type="submit" class="btn btn-outline-danger w-100"><?= e(__('investment.actions.withdraw')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="investmentPause"
     aria-labelledby="investmentPauseLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="investmentPauseLabel"><?= e(__('investment.actions.pause')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <p class="small text-secondary"><?= e(__('investment.pause_hint')) ?></p>
        <form method="post" action="/inversion/pausar">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="investment_pause_reason"><?= e(__('finance.reason')) ?></label>
                <select class="form-select" id="investment_pause_reason" name="motivo" required>
                    <?php foreach (['apuro', 'desempleo', 'otro'] as $reason): ?>
                        <option value="<?= e($reason) ?>"><?= e(__('investment.pause_reasons.' . $reason)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="investment_pause_text"><?= e(__('finance.reason_text')) ?></label>
                <input type="text" class="form-control" id="investment_pause_text" name="motivo_texto" maxlength="255">
            </div>
            <button type="submit" class="btn btn-outline-secondary w-100"><?= e(__('investment.actions.pause')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="investmentAdjust"
     aria-labelledby="investmentAdjustLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="investmentAdjustLabel"><?= e(__('investment.consecutive_adjust')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <p class="small text-secondary"><?= e(__('investment.adjust_hint')) ?></p>
        <form method="post" action="/inversion/porcentaje">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="investment_percent"><?= e(__('investment.adjust_label')) ?></label>
                <div class="input-group">
                    <input type="number" class="form-control" id="investment_percent" name="porcentaje"
                           min="0" max="100" step="1" value="0" required>
                    <span class="input-group-text">%</span>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= e(__('common.actions.save')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="investmentEditContribution"
     aria-labelledby="investmentEditContributionLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="investmentEditContributionLabel"><?= e(__('investment.movements.edit_contribution')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/inversion/aportaciones/editar" data-investment-contribution-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">
            <div class="mb-3">
                <label class="form-label" for="edit_investment_amount"><?= e(__('finance.amount')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="edit_investment_amount" name="monto"
                           min="0.01" step="0.01" inputmode="decimal" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="edit_investment_date"><?= e(__('finance.date')) ?></label>
                <input type="date" class="form-control" id="edit_investment_date" name="fecha" required>
            </div>
            <label class="form-check mb-3">
                <input type="checkbox" class="form-check-input" name="es_extra" value="1" data-edit-investment-extra>
                <span class="form-check-label small"><?= e(__('investment.extra_label')) ?></span>
            </label>
            <button type="submit" class="btn btn-primary w-100"><?= e(__('common.actions.save')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="investmentEditWithdrawal"
     aria-labelledby="investmentEditWithdrawalLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="investmentEditWithdrawalLabel"><?= e(__('investment.movements.edit_withdrawal')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/inversion/retiros/editar" data-investment-withdrawal-edit-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">
            <div class="mb-3">
                <label class="form-label" for="edit_investment_withdraw_amount"><?= e(__('finance.amount')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="edit_investment_withdraw_amount" name="monto"
                           min="0.01" step="0.01" inputmode="decimal" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="edit_investment_reason"><?= e(__('finance.reason')) ?></label>
                <select class="form-select" id="edit_investment_reason" name="motivo" required>
                    <?php foreach ($withdrawReasons as $reason): ?>
                        <option value="<?= e((string) $reason) ?>"><?= e(__('investment.reasons.' . (string) $reason)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="edit_investment_reason_text"><?= e(__('finance.reason_text')) ?></label>
                <input type="text" class="form-control" id="edit_investment_reason_text" name="motivo_texto" maxlength="255">
            </div>
            <div class="mb-3">
                <label class="form-label" for="edit_investment_withdraw_date"><?= e(__('finance.date')) ?></label>
                <input type="date" class="form-control" id="edit_investment_withdraw_date" name="fecha" required>
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= e(__('common.actions.save')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="investmentPosition"
     aria-labelledby="investmentPositionLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="investmentPositionLabel"><?= e(__('investment.positions.title')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/inversion/posiciones" data-position-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">

            <div class="mb-3">
                <label class="form-label" for="position_portfolio"><?= e(__('investment.positions.portfolio')) ?></label>
                <select class="form-select" id="position_portfolio" name="portafolio_id" required>
                    <?php foreach ($portfolios as $portfolio): ?>
                        <option value="<?= (int) $portfolio['id'] ?>"><?= e((string) $portfolio['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label" for="position_name"><?= e(__('investment.positions.name')) ?></label>
                <input type="text" class="form-control" id="position_name" name="nombre" maxlength="120" required>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label" for="position_type"><?= e(__('investment.positions.type')) ?></label>
                    <select class="form-select" id="position_type" name="tipo">
                        <?php foreach ($positionTypes as $type): ?>
                            <option value="<?= e($type) ?>"><?= e(__('investment.portfolio.types.' . $type)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label" for="position_market"><?= e(__('investment.positions.market')) ?></label>
                    <select class="form-select" id="position_market" name="mercado">
                        <?php foreach (['BMV', 'EUA', 'CRIPTO', 'CETES', 'OTRO'] as $market): ?>
                            <option value="<?= e($market) ?>"><?= e(__('investment.positions.markets.' . strtolower($market))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label" for="position_ticker"><?= e(__('investment.positions.ticker')) ?></label>
                    <input type="text" class="form-control" id="position_ticker" name="ticker" maxlength="32">
                </div>
                <div class="col-6">
                    <label class="form-label" for="position_symbol"><?= e(__('investment.positions.symbol')) ?></label>
                    <input type="text" class="form-control" id="position_symbol" name="simbolo_proveedor" maxlength="64">
                </div>
            </div>
            <p class="form-text mt-n2"><?= e(__('investment.positions.symbol_hint')) ?></p>

            <div class="mb-3">
                <?= partial('partials/currency-select', [
                    'name' => 'moneda',
                    'selected' => (is_string($_POST['moneda'] ?? null) ? $_POST['moneda'] : (string) ($currency ?? 'MXN')),
                ]) ?>
            </div>

            <div class="row g-2 mb-1">
                <div class="col-6">
                    <label class="form-label" for="position_quantity"><?= e(__('investment.positions.quantity')) ?></label>
                    <input type="number" class="form-control" id="position_quantity" name="cantidad"
                           min="0.00000001" step="0.00000001" value="1" required>
                </div>
                <div class="col-6">
                    <label class="form-label" for="position_cost"><?= e(__('investment.positions.initial')) ?></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="position_cost" name="costo_total"
                               min="0" step="0.01" inputmode="decimal">
                    </div>
                </div>
            </div>
            <p class="form-text mb-3"><?= e(__('investment.positions.initial_hint')) ?></p>

            <div class="mb-3">
                <label class="form-label" for="position_mode"><?= e(__('investment.positions.mode')) ?></label>
                <select class="form-select" id="position_mode" name="modo_valor">
                    <option value="calculado"><?= e(__('investment.positions.mode_calc')) ?></option>
                    <option value="manual"><?= e(__('investment.positions.mode_manual')) ?></option>
                    <option value="automatico"><?= e(__('investment.positions.mode_auto')) ?></option>
                </select>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label" for="position_rate"><?= e(__('investment.positions.rate')) ?></label>
                    <input type="number" class="form-control" id="position_rate" name="tasa_anual"
                           min="0" max="1000" step="0.0001" inputmode="decimal">
                </div>
                <div class="col-6">
                    <label class="form-label" for="position_maturity"><?= e(__('investment.positions.maturity')) ?></label>
                    <input type="date" class="form-control" id="position_maturity" name="fecha_vencimiento">
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="position_notes"><?= e(__('investment.positions.notes')) ?></label>
                <input type="text" class="form-control" id="position_notes" name="notas" maxlength="255">
            </div>

            <div class="row g-2 mb-3" data-position-manual-wrap>
                <div class="col-7">
                    <label class="form-label" for="position_value"><?= e(__('investment.positions.current_value')) ?></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="position_value" name="valor_manual"
                               min="0" step="0.01" inputmode="decimal">
                    </div>
                </div>
                <div class="col-5">
                    <label class="form-label" for="position_value_date"><?= e(__('finance.date')) ?></label>
                    <input type="date" class="form-control" id="position_value_date" name="fecha_valor">
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100"><?= e(__('common.actions.save')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="investmentPositionValue"
     aria-labelledby="investmentPositionValueLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="investmentPositionValueLabel"><?= e(__('investment.positions.update_value')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <p class="small text-secondary" data-position-value-label></p>
        <form method="post" action="/inversion/posiciones/valor" data-position-value-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">
            <div class="mb-3">
                <label class="form-label" for="position_current_value"><?= e(__('investment.positions.current_value')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="position_current_value" name="valor"
                           min="0.01" step="0.01" inputmode="decimal" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="position_current_date"><?= e(__('finance.date')) ?></label>
                <input type="date" class="form-control" id="position_current_date" name="fecha" value="<?= e($today) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= e(__('common.actions.save')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="investmentPortfolio"
     aria-labelledby="investmentPortfolioLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="investmentPortfolioLabel"
            data-label-new="<?= e(__('investment.portfolios.create')) ?>"
            data-label-edit="<?= e(__('investment.portfolios.edit')) ?>"><?= e(__('investment.portfolios.create')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/inversion/portafolios" data-portfolio-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">
            <div class="mb-3">
                <label class="form-label" for="portfolio_name"><?= e(__('investment.portfolios.name')) ?></label>
                <input type="text" class="form-control" id="portfolio_name" name="nombre" minlength="2" maxlength="80" required
                       placeholder="<?= e(__('investment.portfolios.name_placeholder')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="portfolio_class"><?= e(__('investment.portfolios.class')) ?></label>
                <select class="form-select" id="portfolio_class" name="clase">
                    <?php foreach (\Cauce\Services\PortfolioService::CLASSES as $class): ?>
                        <option value="<?= e($class) ?>"><?= e(__('investment.classes.' . $class)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="portfolio_institution"><?= e(__('investment.portfolios.institution')) ?></label>
                <input type="text" class="form-control" id="portfolio_institution" name="institucion" maxlength="120"
                       placeholder="<?= e(__('investment.portfolios.institution_placeholder')) ?>">
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= e(__('common.actions.save')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="investmentYield"
     aria-labelledby="investmentYieldLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="investmentYieldLabel"><?= e(__('investment.yields.record')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/inversion/rendimientos" data-yield-form>
            <?= csrf_field() ?>
            <?= $targetFields('yield', 'portafolio_id', 'posicion_id') ?>

            <div class="mb-3">
                <label class="form-label" for="yield_type"><?= e(__('investment.yields.type')) ?></label>
                <select class="form-select" id="yield_type" name="tipo" data-yield-type>
                    <?php foreach (\Cauce\Services\PortfolioService::YIELD_TYPES as $type): ?>
                        <option value="<?= e($type) ?>"><?= e(__('investment.yields.types.' . $type)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label" for="yield_amount"><?= e(__('finance.amount')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="yield_amount" name="monto"
                           min="0.01" step="0.01" inputmode="decimal" required>
                </div>
            </div>

            <div class="mb-3">
                <?= partial('partials/currency-select', ['name' => 'moneda', 'selected' => $currency]) ?>
            </div>

            <fieldset class="mb-3" data-yield-destination>
                <legend class="form-label fs-6"><?= e(__('investment.yields.destination')) ?></legend>
                <?php foreach (\Cauce\Services\PortfolioService::DESTINATIONS as $i => $destination): ?>
                    <label class="form-check">
                        <input type="radio" class="form-check-input" name="destino" value="<?= e($destination) ?>" <?= $i === 0 ? 'checked' : '' ?>>
                        <span class="form-check-label"><?= e(__('investment.yields.destinations.' . $destination)) ?></span>
                    </label>
                <?php endforeach; ?>
                <div class="form-text"><?= e(__('investment.yields.destination_hint')) ?></div>
            </fieldset>

            <div class="mb-3">
                <label class="form-label" for="yield_date"><?= e(__('finance.date')) ?></label>
                <input type="date" class="form-control" id="yield_date" name="fecha" value="<?= e($today) ?>" required>
            </div>

            <div class="mb-3">
                <label class="form-label" for="yield_note"><?= e(__('investment.yields.note')) ?></label>
                <input type="text" class="form-control" id="yield_note" name="nota" maxlength="255">
            </div>

            <button type="submit" class="btn btn-primary w-100"><?= e(__('common.actions.save')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="investmentEditYield"
     aria-labelledby="investmentEditYieldLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="investmentEditYieldLabel"><?= e(__('investment.yields.edit')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/inversion/rendimientos/editar" data-yield-edit-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">
            <input type="hidden" name="moneda" value="">
            <p class="small text-secondary" data-yield-paid-note hidden><?= e(__('investment.yields.paid_locked')) ?></p>
            <div class="mb-3">
                <label class="form-label" for="edit_yield_type"><?= e(__('investment.yields.type')) ?></label>
                <select class="form-select" id="edit_yield_type" name="tipo">
                    <?php foreach (\Cauce\Services\PortfolioService::YIELD_TYPES as $type): ?>
                        <option value="<?= e($type) ?>"><?= e(__('investment.yields.types.' . $type)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="edit_yield_amount"><?= e(__('finance.amount')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="edit_yield_amount" name="monto"
                           min="0.01" step="0.01" inputmode="decimal" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="edit_yield_date"><?= e(__('finance.date')) ?></label>
                <input type="date" class="form-control" id="edit_yield_date" name="fecha" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="edit_yield_note"><?= e(__('investment.yields.note')) ?></label>
                <input type="text" class="form-control" id="edit_yield_note" name="nota" maxlength="255">
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= e(__('common.actions.save')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="investmentMove"
     aria-labelledby="investmentMoveLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="investmentMoveLabel"><?= e(__('investment.portfolios.move')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <p class="small text-secondary" data-move-label></p>
        <form method="post" action="/inversion/posiciones/mover" data-move-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">
            <div class="mb-3">
                <label class="form-label" for="move_portfolio"><?= e(__('investment.positions.portfolio')) ?></label>
                <select class="form-select" id="move_portfolio" name="portafolio_id" required>
                    <?php foreach ($portfolios as $portfolio): ?>
                        <option value="<?= (int) $portfolio['id'] ?>"><?= e((string) $portfolio['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= e(__('investment.portfolios.move_submit')) ?></button>
        </form>
    </div>
</div>
