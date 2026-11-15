<?php
$currencies = $currencies ?? [];
$available = $available ?? [];
$principal = (string) ($principal ?? 'MXN');
$rates = $rates ?? [];
?>
<div class="page-heading">
    <div>
        <h1 class="h3 mb-1"><?= e(__('currency.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('currency.subtitle')) ?></p>
    </div>
    <a href="/ajustes/perfil" class="btn btn-outline-secondary"><?= e(__('common.actions.back')) ?></a>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-7">
        <div class="surface-card h-100">
            <h2 class="h5 mb-1"><?= e(__('currency.active_title')) ?></h2>
            <p class="small text-secondary mb-3"><?= e(__('currency.active_hint')) ?></p>

            <div class="d-flex flex-column gap-2">
                <?php foreach ($currencies as $currency): ?>
                    <?php $code = (string) $currency['codigo']; ?>
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 border rounded-3 p-2">
                        <div>
                            <span class="fw-semibold"><?= e($code) ?></span>
                            <?php if ($code === $principal): ?>
                                <span class="badge text-bg-primary ms-1"><?= e(__('currency.principal')) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <?php if ($code !== $principal): ?>
                                <form method="post" action="/ajustes/monedas/principal" class="m-0"
                                      data-confirm="<?= e(__('currency.confirm_principal')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="codigo" value="<?= e($code) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                        <?= e(__('currency.make_principal')) ?>
                                    </button>
                                </form>
                                <form method="post" action="/ajustes/monedas/quitar" class="m-0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="codigo" value="<?= e($code) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <?= e(__('currency.remove')) ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="surface-card h-100">
            <h2 class="h5 mb-1"><?= e(__('currency.add_title')) ?></h2>
            <p class="small text-secondary mb-3"><?= e(__('currency.add_hint')) ?></p>

            <?php if ($available === []): ?>
                <p class="small text-secondary mb-0"><?= e(__('currency.no_available')) ?></p>
            <?php else: ?>
                <form method="post" action="/ajustes/monedas/agregar" class="row g-2">
                    <?= csrf_field() ?>
                    <div class="col-8">
                        <select class="form-select" name="codigo" aria-label="<?= e(__('currency.label')) ?>">
                            <?php foreach ($available as $code): ?>
                                <option value="<?= e($code) ?>"><?= e($code) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn btn-primary w-100"><?= e(__('currency.add')) ?></button>
                    </div>
                </form>
            <?php endif; ?>

            <hr class="my-4">

            <p class="small text-secondary mb-0"><?= e(__('currency.rates_admin_hint')) ?></p>
        </div>
    </div>
</div>

<div class="surface-card">
    <h2 class="h5 mb-1"><?= e(__('currency.rates_title')) ?></h2>
    <p class="small text-secondary mb-3"><?= e(__('currency.rates_hint')) ?></p>

    <?php if ($rates === []): ?>
        <p class="small text-secondary mb-0"><?= e(__('currency.no_rates')) ?></p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= e(__('currency.rate_date')) ?></th>
                        <th><?= e(__('currency.rate_pair')) ?></th>
                        <th class="text-end"><?= e(__('currency.rate_value')) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rates as $rate): ?>
                        <tr>
                            <td class="small text-secondary"><?= e(format_date($rate['fecha'] . ' 12:00:00')) ?></td>
                            <td><?= e((string) $rate['moneda_origen']) ?> → <?= e((string) $rate['moneda_destino']) ?></td>
                            <td class="text-end"><?= e(rtrim(rtrim(number_format((float) $rate['tasa'], 8, '.', ''), '0'), '.')) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
