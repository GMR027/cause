<?php

$instruments = $instruments ?? [];
$withdrawReasons = $withdrawReasons ?? [];
$currency = (string) ($currency ?? 'MXN');
$today = (string) ($today ?? date('Y-m-d'));
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
                    'selected' => (string) ($_POST['moneda'] ?? ($currency ?? 'MXN')),
                ]) ?>
            </div>

            <div class="mb-3">
                <label class="form-label" for="investment_instrument"><?= e(__('investment.instrument')) ?></label>
                <select class="form-select" id="investment_instrument" name="instrumento" required>
                    <?php foreach ($instruments as $instrument): ?>
                        <option value="<?= e((string) $instrument) ?>"><?= e(__('investment.instruments.' . (string) $instrument)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

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

            <div class="mb-3">
                <label class="form-label" for="investment_withdraw_instrument"><?= e(__('investment.instrument')) ?></label>
                <select class="form-select" id="investment_withdraw_instrument" name="instrumento" required>
                    <?php foreach ($instruments as $instrument): ?>
                        <option value="<?= e((string) $instrument) ?>"><?= e(__('investment.instruments.' . (string) $instrument)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

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
                    'selected' => (string) ($_POST['moneda'] ?? ($currency ?? 'MXN')),
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

            <div class="mb-3" data-rebalance-target hidden>
                <label class="form-label" for="investment_target"><?= e(__('investment.rebalance_target')) ?></label>
                <select class="form-select" id="investment_target" name="destino_instrumento">
                    <?php foreach ($instruments as $instrument): ?>
                        <option value="<?= e((string) $instrument) ?>"><?= e(__('investment.instruments.' . (string) $instrument)) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text"><?= e(__('investment.rebalance_hint')) ?></div>
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
        <h2 class="h5 mb-0" id="investmentPositionLabel"><?= e(__('investment.positions.create')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/inversion/posiciones" data-position-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">

            <div class="mb-3">
                <label class="form-label" for="position_name"><?= e(__('investment.positions.name')) ?></label>
                <input type="text" class="form-control" id="position_name" name="nombre" maxlength="120" required>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label" for="position_type"><?= e(__('investment.positions.type')) ?></label>
                    <select class="form-select" id="position_type" name="tipo">
                        <?php foreach (['accion', 'etf', 'cripto', 'cetes', 'fondo', 'otro'] as $type): ?>
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
                    'selected' => (string) ($_POST['moneda'] ?? ($currency ?? 'MXN')),
                ]) ?>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label" for="position_quantity"><?= e(__('investment.positions.quantity')) ?></label>
                    <input type="number" class="form-control" id="position_quantity" name="cantidad"
                           min="0.00000001" step="0.00000001" value="1" required>
                </div>
                <div class="col-6">
                    <label class="form-label" for="position_cost"><?= e(__('investment.positions.cost')) ?></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="position_cost" name="costo_total"
                               min="0" step="0.01" inputmode="decimal" required>
                    </div>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="position_mode"><?= e(__('investment.positions.mode')) ?></label>
                <select class="form-select" id="position_mode" name="modo_valor">
                    <option value="manual"><?= e(__('investment.positions.mode_manual')) ?></option>
                    <option value="automatico"><?= e(__('investment.positions.mode_auto')) ?></option>
                </select>
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
