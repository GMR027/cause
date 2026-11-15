<?php

$currency = (string) ($currency ?? 'MXN');
$today = (string) ($today ?? date('Y-m-d'));
?>
<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="sharingCreate"
     aria-labelledby="sharingCreateLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="sharingCreateLabel"><?= e(__('sharing.actions.create')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/compartir">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="sharing_amount"><?= e(__('finance.amount')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="sharing_amount" name="monto"
                           min="0.01" step="0.01" inputmode="decimal" required>
                </div>
            </div>
            <div class="mb-3">
                <?= partial('partials/currency-select', [
                    'name' => 'moneda',
                    'selected' => $currency,
                ]) ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="sharing_recipient"><?= e(__('sharing.recipient')) ?></label>
                <input type="text" class="form-control" id="sharing_recipient" name="destinatario" maxlength="120"
                       placeholder="<?= e(__('sharing.recipient_placeholder')) ?>">
            </div>
            <div class="mb-3">
                <label class="form-label" for="sharing_date"><?= e(__('finance.date')) ?></label>
                <input type="date" class="form-control" id="sharing_date" name="fecha" value="<?= e($today) ?>" required>
            </div>
            <label class="form-check mb-2">
                <input type="checkbox" class="form-check-input" name="es_extra" value="1">
                <span class="form-check-label"><?= e(__('sharing.extra_label')) ?></span>
            </label>
            <p class="small text-secondary"><?= e(__('sharing.extra_hint')) ?></p>
            <button type="submit" class="btn btn-primary w-100"><?= e(__('sharing.actions.create')) ?></button>
        </form>
    </div>
</div>

<div class="offcanvas offcanvas-bottom finance-sheet" tabindex="-1" id="sharingEdit"
     aria-labelledby="sharingEditLabel">
    <div class="offcanvas-header">
        <h2 class="h5 mb-0" id="sharingEditLabel"><?= e(__('sharing.edit')) ?></h2>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="<?= e(__('common.actions.close')) ?>"></button>
    </div>
    <div class="offcanvas-body">
        <form method="post" action="/compartir/editar" data-sharing-form>
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="">
            <div class="mb-3">
                <label class="form-label" for="edit_sharing_amount"><?= e(__('finance.amount')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input type="number" class="form-control" id="edit_sharing_amount" name="monto"
                           min="0.01" step="0.01" inputmode="decimal" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label" for="edit_sharing_recipient"><?= e(__('sharing.recipient')) ?></label>
                <input type="text" class="form-control" id="edit_sharing_recipient" name="destinatario" maxlength="120">
            </div>
            <div class="mb-3">
                <label class="form-label" for="edit_sharing_date"><?= e(__('finance.date')) ?></label>
                <input type="date" class="form-control" id="edit_sharing_date" name="fecha" required>
            </div>
            <label class="form-check mb-3">
                <input type="checkbox" class="form-check-input" name="es_extra" value="1">
                <span class="form-check-label small"><?= e(__('sharing.extra_label')) ?></span>
            </label>
            <button type="submit" class="btn btn-primary w-100"><?= e(__('common.actions.save')) ?></button>
        </form>
    </div>
</div>
