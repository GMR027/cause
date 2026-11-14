<?php

$activeMetas = $activeMetas ?? [];
$metas = $metas ?? [];
$accounts = $accounts ?? [];
$defaultAccountId = (int) ($default_account_id ?? 0);
$metaTypes = $metaTypes ?? [];
$withdrawReasons = $withdrawReasons ?? [];
$currency = (string) ($currency ?? 'MXN');
$today = (string) ($today ?? date('Y-m-d'));
?>
<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="savingsContribute"
     aria-labelledby="savingsContributeLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="savingsContributeLabel"><?= e(__('savings.actions.contribute')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/ahorro/aportar">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label" for="savings_account"><?= e(__('savings.accounts.label')) ?></label>
                <select class="form-select" id="savings_account" name="cuenta_id" data-account-select required>
                    <?php foreach ($accounts as $account): ?>
                        <?php if ((int) $account['activa'] !== 1) { continue; } ?>
                        <option value="<?= (int) $account['id'] ?>" <?= (int) $account['id'] === $defaultAccountId ? 'selected' : '' ?>>
                            <?= e((string) $account['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label" for="savings_meta"><?= e(__('savings.meta.target')) ?></label>
                <select class="form-select" id="savings_meta" name="meta_id" data-meta-select>
                    <option value=""><?= e(__('savings.meta.none')) ?></option>
                    <?php foreach ($activeMetas as $meta): ?>
                        <option value="<?= (int) $meta['id'] ?>" data-account="<?= (int) ($meta['cuenta_id'] ?? 0) ?>">
                            <?= e((string) $meta['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label" for="savings_amount"><?= e(__('finance.amount')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="savings_amount" name="monto"
                           min="0.01" step="0.01" inputmode="decimal" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="savings_date"><?= e(__('finance.date')) ?></label>
                <input type="date" class="form-control" id="savings_date" name="fecha" value="<?= e($today) ?>" required>
            </div>

            <button type="submit" class="btn btn-primary w-100"><?= e(__('savings.actions.contribute')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="savingsWithdraw"
     aria-labelledby="savingsWithdrawLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="savingsWithdrawLabel"><?= e(__('savings.actions.withdraw')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/ahorro/retirar">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label" for="savings_withdraw_account"><?= e(__('savings.accounts.label')) ?></label>
                <select class="form-select" id="savings_withdraw_account" name="cuenta_id" data-account-select required>
                    <?php foreach ($accounts as $account): ?>
                        <?php if ((int) $account['activa'] !== 1) { continue; } ?>
                        <option value="<?= (int) $account['id'] ?>" <?= (int) $account['id'] === $defaultAccountId ? 'selected' : '' ?>>
                            <?= e((string) $account['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label" for="savings_withdraw_meta"><?= e(__('savings.meta.target')) ?></label>
                <select class="form-select" id="savings_withdraw_meta" name="meta_id" data-meta-select>
                    <option value=""><?= e(__('savings.meta.none')) ?></option>
                    <?php foreach ($metas as $meta): ?>
                        <option value="<?= (int) $meta['id'] ?>" data-account="<?= (int) ($meta['cuenta_id'] ?? 0) ?>">
                            <?= e((string) $meta['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label" for="savings_withdraw_amount"><?= e(__('finance.amount')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="savings_withdraw_amount" name="monto"
                           min="0.01" step="0.01" inputmode="decimal" required>
                </div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="savings_reason"><?= e(__('finance.reason')) ?></label>
                <select class="form-select" id="savings_reason" name="motivo" required>
                    <?php foreach ($withdrawReasons as $reason): ?>
                        <option value="<?= e((string) $reason) ?>"><?= e(__('savings.reasons.' . (string) $reason)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="mb-3">
                <label class="form-label" for="savings_reason_text"><?= e(__('finance.reason_text')) ?></label>
                <input type="text" class="form-control" id="savings_reason_text" name="motivo_texto" maxlength="255">
            </div>

            <div class="mb-3">
                <label class="form-label" for="savings_withdraw_date"><?= e(__('finance.date')) ?></label>
                <input type="date" class="form-control" id="savings_withdraw_date" name="fecha" value="<?= e($today) ?>" required>
            </div>

            <label class="form-check mb-3">
                <input type="checkbox" class="form-check-input" name="confirmar" value="1" required>
                <span class="form-check-label small"><?= e(__('savings.confirm_withdraw')) ?></span>
            </label>

            <button type="submit" class="btn btn-outline-danger w-100"><?= e(__('savings.actions.withdraw')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="savingsMeta"
     aria-labelledby="savingsMetaLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="savingsMetaLabel"><?= e(__('savings.meta.create')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/ahorro/metas" data-meta-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">

            <div class="mb-3">
                <label class="form-label" for="meta_name"><?= e(__('savings.meta.name')) ?></label>
                <input type="text" class="form-control" id="meta_name" name="nombre" maxlength="120" required>
            </div>

            <div class="mb-3">
                <label class="form-label" for="meta_account"><?= e(__('savings.accounts.label')) ?></label>
                <select class="form-select" id="meta_account" name="cuenta_id" data-meta-account-select required>
                    <?php foreach ($accounts as $account): ?>
                        <?php if ((int) $account['activa'] !== 1) { continue; } ?>
                        <option value="<?= (int) $account['id'] ?>" <?= (int) $account['id'] === $defaultAccountId ? 'selected' : '' ?>>
                            <?= e((string) $account['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text"><?= e(__('savings.meta.account_hint')) ?></div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-7">
                    <label class="form-label" for="meta_type"><?= e(__('savings.meta.type')) ?></label>
                    <select class="form-select" id="meta_type" name="tipo">
                        <?php foreach ($metaTypes as $type): ?>
                            <option value="<?= e((string) $type) ?>"><?= e(__('savings.types.' . (string) $type)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-5">
                    <label class="form-label" for="meta_objective"><?= e(__('savings.meta.objective')) ?></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" class="form-control" id="meta_objective" name="objetivo"
                               min="0.01" step="0.01" inputmode="decimal" required>
                    </div>
                </div>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-7">
                    <label class="form-label" for="meta_date"><?= e(__('savings.meta.target_date')) ?></label>
                    <input type="date" class="form-control" id="meta_date" name="fecha_objetivo">
                </div>
                <div class="col-5">
                    <label class="form-label" for="meta_months"><?= e(__('savings.meta.months')) ?></label>
                    <input type="number" class="form-control" id="meta_months" name="plazo_meses" min="1" max="120">
                </div>
            </div>

            <label class="form-check mb-3">
                <input type="checkbox" class="form-check-input" name="activa" value="1" checked>
                <span class="form-check-label small"><?= e(__('savings.meta.active')) ?></span>
            </label>

            <button type="submit" class="btn btn-primary w-100"><?= e(__('common.actions.save')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="savingsEditContribution"
     aria-labelledby="savingsEditContributionLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="savingsEditContributionLabel"><?= e(__('savings.movements.edit_contribution')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/ahorro/aportaciones/editar" data-contribution-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">
            <div class="mb-3">
                <label class="form-label" for="edit_contribution_amount"><?= e(__('finance.amount')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="edit_contribution_amount" name="monto"
                           min="0.01" step="0.01" inputmode="decimal" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="edit_contribution_date"><?= e(__('finance.date')) ?></label>
                <input type="date" class="form-control" id="edit_contribution_date" name="fecha" required>
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= e(__('common.actions.save')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="savingsEditWithdrawal"
     aria-labelledby="savingsEditWithdrawalLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="savingsEditWithdrawalLabel"><?= e(__('savings.movements.edit_withdrawal')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/ahorro/retiros/editar" data-withdrawal-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">
            <div class="mb-3">
                <label class="form-label" for="edit_withdrawal_amount"><?= e(__('finance.amount')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="edit_withdrawal_amount" name="monto"
                           min="0.01" step="0.01" inputmode="decimal" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="edit_withdrawal_reason"><?= e(__('finance.reason')) ?></label>
                <select class="form-select" id="edit_withdrawal_reason" name="motivo" required>
                    <?php foreach ($withdrawReasons as $reason): ?>
                        <option value="<?= e((string) $reason) ?>"><?= e(__('savings.reasons.' . (string) $reason)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="edit_withdrawal_text"><?= e(__('finance.reason_text')) ?></label>
                <input type="text" class="form-control" id="edit_withdrawal_text" name="motivo_texto" maxlength="255">
            </div>
            <div class="mb-3">
                <label class="form-label" for="edit_withdrawal_date"><?= e(__('finance.date')) ?></label>
                <input type="date" class="form-control" id="edit_withdrawal_date" name="fecha" required>
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= e(__('common.actions.save')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="savingsAccount"
     aria-labelledby="savingsAccountLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="savingsAccountLabel"><?= e(__('savings.accounts.create')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/ahorro/cuentas" data-account-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">

            <div class="mb-3">
                <label class="form-label" for="account_name"><?= e(__('savings.accounts.name')) ?></label>
                <input type="text" class="form-control" id="account_name" name="nombre" maxlength="120" required>
            </div>

            <div class="row g-2 mb-3">
                <div class="col-6">
                    <label class="form-label" for="account_type"><?= e(__('savings.accounts.type')) ?></label>
                    <select class="form-select" id="account_type" name="tipo">
                        <?php foreach (['banco', 'efectivo', 'cooperativa', 'otro'] as $type): ?>
                            <option value="<?= e($type) ?>"><?= e(__('savings.accounts.type_' . $type)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label" for="account_institution"><?= e(__('savings.accounts.institution')) ?></label>
                    <input type="text" class="form-control" id="account_institution" name="institucion" maxlength="120">
                </div>
            </div>

            <div class="mb-3" data-account-currency-wrap>
                <?= partial('partials/currency-select', [
                    'name' => 'moneda',
                    'selected' => (string) ($_POST['moneda'] ?? ($currency ?? 'MXN')),
                ]) ?>
            </div>

            <div class="mb-3" data-account-initial-wrap>
                <label class="form-label" for="account_initial"><?= e(__('savings.accounts.initial')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="account_initial" name="saldo_inicial"
                           min="0" step="0.01" inputmode="decimal" value="0">
                </div>
                <div class="form-text"><?= e(__('savings.accounts.initial_hint')) ?></div>
            </div>

            <label class="form-check mb-2" data-account-active-wrap>
                <input type="checkbox" class="form-check-input" name="activa" value="1" checked>
                <span class="form-check-label small"><?= e(__('savings.accounts.active')) ?></span>
            </label>

            <label class="form-check mb-3">
                <input type="checkbox" class="form-check-input" name="predeterminada" value="1">
                <span class="form-check-label small"><?= e(__('savings.accounts.make_default')) ?></span>
            </label>

            <button type="submit" class="btn btn-primary w-100"><?= e(__('common.actions.save')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="savingsTransfer"
     aria-labelledby="savingsTransferLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="savingsTransferLabel"><?= e(__('savings.accounts.transfer')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/ahorro/transferir">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="transfer_origin"><?= e(__('savings.accounts.origin')) ?></label>
                <select class="form-select" id="transfer_origin" name="cuenta_origen_id" required>
                    <?php foreach ($accounts as $account): ?>
                        <?php if ((int) $account['activa'] !== 1) { continue; } ?>
                        <option value="<?= (int) $account['id'] ?>"><?= e((string) $account['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="transfer_destination"><?= e(__('savings.accounts.destination')) ?></label>
                <select class="form-select" id="transfer_destination" name="cuenta_destino_id" required>
                    <?php foreach ($accounts as $account): ?>
                        <?php if ((int) $account['activa'] !== 1) { continue; } ?>
                        <option value="<?= (int) $account['id'] ?>"><?= e((string) $account['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="transfer_amount"><?= e(__('finance.amount')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="transfer_amount" name="monto"
                           min="0.01" step="0.01" inputmode="decimal" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="transfer_date"><?= e(__('finance.date')) ?></label>
                <input type="date" class="form-control" id="transfer_date" name="fecha" value="<?= e($today) ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="transfer_note"><?= e(__('savings.accounts.note')) ?></label>
                <input type="text" class="form-control" id="transfer_note" name="nota" maxlength="255">
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= e(__('savings.accounts.transfer')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="savingsAssign"
     aria-labelledby="savingsAssignLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="savingsAssignLabel"><?= e(__('savings.accounts.assign')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/ahorro/asignar">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="assign_account"><?= e(__('savings.accounts.label')) ?></label>
                <select class="form-select" id="assign_account" name="cuenta_id" data-account-select required>
                    <?php foreach ($accounts as $account): ?>
                        <?php if ((int) $account['activa'] !== 1) { continue; } ?>
                        <option value="<?= (int) $account['id'] ?>" <?= (int) $account['id'] === $defaultAccountId ? 'selected' : '' ?>>
                            <?= e((string) $account['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="assign_meta"><?= e(__('savings.meta.target')) ?></label>
                <select class="form-select" id="assign_meta" name="meta_id" data-meta-select required>
                    <?php foreach ($activeMetas as $meta): ?>
                        <option value="<?= (int) $meta['id'] ?>" data-account="<?= (int) ($meta['cuenta_id'] ?? 0) ?>">
                            <?= e((string) $meta['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="assign_amount"><?= e(__('finance.amount')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="assign_amount" name="monto"
                           min="0.01" step="0.01" inputmode="decimal" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="assign_date"><?= e(__('finance.date')) ?></label>
                <input type="date" class="form-control" id="assign_date" name="fecha" value="<?= e($today) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= e(__('savings.accounts.assign')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="savingsMoveMeta"
     aria-labelledby="savingsMoveMetaLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="savingsMoveMetaLabel"><?= e(__('savings.meta.move')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <p class="small text-secondary" data-move-meta-label></p>
        <form method="post" action="/ahorro/metas/mover">
            <?= csrf_field() ?>
            <input type="hidden" name="meta_id" value="">
            <div class="mb-3">
                <label class="form-label" for="move_meta_destination"><?= e(__('savings.accounts.destination')) ?></label>
                <select class="form-select" id="move_meta_destination" name="cuenta_destino_id" required>
                    <?php foreach ($accounts as $account): ?>
                        <?php if ((int) $account['activa'] !== 1) { continue; } ?>
                        <option value="<?= (int) $account['id'] ?>"><?= e((string) $account['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="move_meta_date"><?= e(__('finance.date')) ?></label>
                <input type="date" class="form-control" id="move_meta_date" name="fecha" value="<?= e($today) ?>" required>
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= e(__('savings.meta.move')) ?></button>
        </form>
    </div>
</div>
