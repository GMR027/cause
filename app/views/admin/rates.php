<?php
$rates = $rates ?? [];
$editing = $editing ?? null;
$isEditing = is_array($editing);
$currencies = $currencies ?? ['MXN', 'USD', 'EUR'];
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('currency.admin.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('currency.admin.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('currency.admin.subtitle')) ?></p>
    </div>
    <form method="post" action="/admin/monedas/recalcular" class="m-0">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-secondary"><?= e(__('currency.admin.recalculate')) ?></button>
    </form>
</div>

<div class="surface-card mb-4">
    <h2 class="h5 mb-3"><?= e($isEditing ? __('currency.admin.edit_title') : __('currency.admin.create_title')) ?></h2>
    <form method="post" action="/admin/monedas/guardar" class="row g-3">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $isEditing ? (int) $editing['id'] : 0 ?>">
        <div class="col-12 col-md-3">
            <label class="form-label" for="rate-from"><?= e(__('currency.admin.from')) ?></label>
            <select class="form-select" id="rate-from" name="moneda_origen">
                <?php foreach ($currencies as $code): ?>
                    <option value="<?= e($code) ?>" <?= (string) ($editing['moneda_origen'] ?? '') === $code ? 'selected' : '' ?>><?= e($code) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label" for="rate-to"><?= e(__('currency.admin.to')) ?></label>
            <select class="form-select" id="rate-to" name="moneda_destino">
                <?php foreach ($currencies as $code): ?>
                    <option value="<?= e($code) ?>" <?= (string) ($editing['moneda_destino'] ?? 'MXN') === $code ? 'selected' : '' ?>><?= e($code) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="rate-value"><?= e(__('currency.admin.value')) ?></label>
            <input type="text" inputmode="decimal" class="form-control" id="rate-value" name="tasa" value="<?= $isEditing ? e(rtrim(rtrim(number_format((float) $editing['tasa'], 8, '.', ''), '0'), '.')) : '' ?>" required>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label" for="rate-date"><?= e(__('currency.admin.date')) ?></label>
            <input type="date" class="form-control" id="rate-date" name="fecha"
                   value="<?= e((string) ($editing['fecha'] ?? gmdate('Y-m-d'))) ?>" required>
        </div>
        <div class="col-12 col-md-2 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100"><?= e(__('currency.admin.save')) ?></button>
        </div>
        <?php if ($isEditing): ?>
        <div class="col-12">
            <a href="/admin/monedas" class="btn btn-outline-secondary"><?= e(__('common.actions.cancel')) ?></a>
        </div>
        <?php endif; ?>
    </form>
    <p class="small text-secondary mt-2 mb-0"><?= e(__('currency.admin.hint')) ?></p>
</div>

<div class="surface-card">
    <h2 class="h5 mb-3"><?= e(__('currency.admin.list_title')) ?></h2>

    <?php if ($rates === []): ?>
        <p class="small text-secondary mb-0"><?= e(__('currency.admin.empty')) ?></p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= e(__('currency.admin.date')) ?></th>
                        <th><?= e(__('currency.admin.from')) ?></th>
                        <th><?= e(__('currency.admin.to')) ?></th>
                        <th class="text-end"><?= e(__('currency.admin.value')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rates as $rate): ?>
                        <tr>
                            <td class="small text-secondary"><?= e(format_date($rate['fecha'] . ' 12:00:00')) ?></td>
                            <td><?= e((string) $rate['moneda_origen']) ?></td>
                            <td><?= e((string) $rate['moneda_destino']) ?></td>
                            <td class="text-end"><?= e(rtrim(rtrim(number_format((float) $rate['tasa'], 8, '.', ''), '0'), '.')) ?></td>
                            <td class="text-end">
                                <div class="d-flex justify-content-end gap-2">
                                <a href="/admin/monedas?editar=<?= (int) $rate['id'] ?>" class="btn btn-sm btn-outline-secondary"><?= e(__('common.actions.edit')) ?></a>
                                <form method="post" action="/admin/monedas/eliminar" class="m-0"
                                      onsubmit="return confirm('<?= e(__('currency.admin.confirm_delete')) ?>')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= (int) $rate['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <?= e(__('common.actions.delete')) ?>
                                    </button>
                                </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
