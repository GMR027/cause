<?php

$currency = (string) ($currency ?? 'MXN');
$today = (string) ($today ?? date('Y-m-d'));
?>
<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="donationDonate"
     aria-labelledby="donationDonateLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="donationDonateLabel"><?= e(__('donation.actions.donate')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/donacion/donar">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="donation_amount"><?= e(__('finance.amount')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="donation_amount" name="monto"
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
                <label class="form-label" for="donation_cause"><?= e(__('donation.cause')) ?></label>
                <input type="text" class="form-control" id="donation_cause" name="causa" maxlength="120"
                       placeholder="<?= e(__('donation.cause_placeholder')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="donation_date"><?= e(__('finance.date')) ?></label>
                <input type="date" class="form-control" id="donation_date" name="fecha" value="<?= e($today) ?>" required>
            </div>
            <label class="form-check mb-2">
                <input type="checkbox" class="form-check-input" name="es_extra" value="1">
                <span class="form-check-label"><?= e(__('donation.extra_label')) ?></span>
            </label>
            <p class="small text-secondary"><?= e(__('donation.extra_hint')) ?></p>
            <button type="submit" class="btn btn-primary w-100"><?= e(__('donation.actions.donate')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="donationEdit"
     aria-labelledby="donationEditLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="donationEditLabel"><?= e(__('donation.edit')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/donacion/editar" data-donation-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">
            <div class="mb-3">
                <label class="form-label" for="edit_donation_amount"><?= e(__('finance.amount')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="edit_donation_amount" name="monto"
                           min="0.01" step="0.01" inputmode="decimal" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="edit_donation_cause"><?= e(__('donation.cause')) ?></label>
                <input type="text" class="form-control" id="edit_donation_cause" name="causa" maxlength="120">
            </div>
            <div class="mb-3">
                <label class="form-label" for="edit_donation_date"><?= e(__('finance.date')) ?></label>
                <input type="date" class="form-control" id="edit_donation_date" name="fecha" required>
            </div>
            <label class="form-check mb-3">
                <input type="checkbox" class="form-check-input" name="es_extra" value="1" data-edit-donation-extra>
                <span class="form-check-label small"><?= e(__('donation.extra_label')) ?></span>
            </label>
            <button type="submit" class="btn btn-primary w-100"><?= e(__('common.actions.save')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="donationActivate"
     aria-labelledby="donationActivateLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="donationActivateLabel"><?= e(__('donation.actions.activate')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <p class="small text-secondary"><?= e(__('donation.activate_hint')) ?></p>
        <form method="post" action="/donacion/activar">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="donation_percent"><?= e(__('donation.activate_label')) ?></label>
                <div class="input-group">
                    <input type="number" class="form-control" id="donation_percent" name="porcentaje"
                           min="0" max="100" step="1" value="5" required>
                    <span class="input-group-text">%</span>
                </div>
                <div class="form-text"><?= e(__('donation.activate_future')) ?></div>
            </div>
            <button type="submit" class="btn btn-primary w-100"><?= e(__('donation.actions.activate')) ?></button>
        </form>
    </div>
</div>
