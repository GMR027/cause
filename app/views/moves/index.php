<?php

$currency = (string) ($currency ?? 'MXN');
$categories = $categories ?? [];
$moves = $moves ?? [];
$reasons = $reasons ?? [];
$period = $period ?? null;
$timezone = new DateTimeZone((string) ($currentUser['zona_horaria'] ?? config('app.timezone', 'UTC')));
$today = (new DateTimeImmutable('now', $timezone))->format('Y-m-d');
$originDefault = '';
$destinationDefault = '';

foreach ($categories as $category) {
    if ($originDefault === '' && (int) $category['disponible'] > 0) {
        $originDefault = (string) $category['nombre'];
        continue;
    }

    if ($originDefault !== '' && (string) $category['nombre'] !== $originDefault) {
        $destinationDefault = (string) $category['nombre'];
        break;
    }
}
?>
<div class="page-heading">
    <div>
        <span class="badge phase-badge mb-2"><?= e(__('moves.badge')) ?></span>
        <h1 class="h3 mb-1"><?= e(__('moves.title')) ?></h1>
        <p class="text-secondary mb-0"><?= e(__('moves.subtitle')) ?></p>
        <?php if ($period !== null): ?>
            <p class="small text-secondary mb-0 mt-1">
                <?= e(format_date($period['inicio'] ?? null)) ?> – <?= e(format_date($period['fin'] ?? null)) ?>
            </p>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-12 col-xl-5">
        <div class="surface-card h-100">
            <h2 class="h5 mb-1"><?= e(__('moves.form.title')) ?></h2>
            <p class="small text-secondary"><?= e(__('moves.form.hint')) ?></p>

            <form method="post" action="/mover" class="row g-3" data-move-form>
                <?= csrf_field() ?>
                <div class="col-12 col-sm-6">
                    <label class="form-label" for="move-origin"><?= e(__('moves.form.origin')) ?></label>
                    <select class="form-select" id="move-origin" name="origen" data-move-origin required>
                        <option value=""><?= e(__('moves.form.select')) ?></option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e((string) $category['nombre']) ?>"
                                    data-available="<?= (int) $category['disponible'] ?>"
                                    <?= (string) $category['nombre'] === $originDefault ? 'selected' : '' ?>>
                                <?= e((string) $category['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="form-text" data-move-available>
                        <?= e(__('moves.form.available', [
                            ':amount' => format_money((int) ($categories[0]['disponible'] ?? 0), $currency),
                        ])) ?>
                    </span>
                </div>

                <div class="col-12 col-sm-6">
                    <label class="form-label" for="move-destination"><?= e(__('moves.form.destination')) ?></label>
                    <select class="form-select" id="move-destination" name="destino" required>
                        <option value=""><?= e(__('moves.form.select')) ?></option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e((string) $category['nombre']) ?>"
                                    <?= (string) $category['nombre'] === $destinationDefault ? 'selected' : '' ?>>
                                <?= e((string) $category['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-sm-6">
                    <label class="form-label" for="move-amount"><?= e(__('moves.form.amount')) ?></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="text" inputmode="decimal" class="form-control" id="move-amount"
                               name="monto" placeholder="0.00" required>
                        <span class="input-group-text"><?= e($currency) ?></span>
                    </div>
                </div>

                <div class="col-12 col-sm-6">
                    <label class="form-label" for="move-date"><?= e(__('moves.form.date')) ?></label>
                    <input type="date" class="form-control" id="move-date" name="fecha"
                           value="<?= e($today) ?>" max="<?= e($today) ?>" required>
                </div>

                <div class="col-12 col-sm-6">
                    <label class="form-label" for="move-reason"><?= e(__('moves.form.reason')) ?></label>
                    <select class="form-select" id="move-reason" name="motivo" required>
                        <?php foreach ($reasons as $reason): ?>
                            <option value="<?= e((string) $reason) ?>">
                                <?= e(__('moves.reasons.' . (string) $reason)) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-sm-6">
                    <label class="form-label" for="move-text"><?= e(__('moves.form.reason_text')) ?></label>
                    <input type="text" class="form-control" id="move-text" name="motivo_texto"
                           maxlength="255" placeholder="<?= e(__('moves.form.reason_placeholder')) ?>">
                </div>

                <div class="col-12">
                    <div class="alert alert-warning d-none py-2" data-move-warning>
                        <?= e(__('moves.form.same_warning')) ?>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-arrow-left-right me-1" aria-hidden="true"></i>
                        <?= e(__('moves.form.submit')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="col-12 col-xl-7">
        <div class="surface-card h-100">
            <div class="d-flex align-items-center justify-content-between mb-2">
                <h2 class="h5 mb-0"><?= e(__('moves.columns.category')) ?></h2>
                <span class="small text-secondary"><?= e(__('finance.available')) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col"><?= e(__('moves.columns.category')) ?></th>
                            <th scope="col" class="text-end"><?= e(__('moves.columns.adjusted')) ?></th>
                            <th scope="col" class="text-end"><?= e(__('moves.columns.spent')) ?></th>
                            <th scope="col" class="text-end"><?= e(__('moves.columns.available')) ?></th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr>
                                <td>
                                    <span class="fw-semibold d-block"><?= e((string) $category['nombre']) ?></span>
                                    <?php if ((int) $category['entradas'] > 0 || (int) $category['salidas'] > 0): ?>
                                        <span class="small text-secondary">
                                            <?php if ((int) $category['entradas'] > 0): ?>
                                                +<?= money_html((int) $category['entradas'], $currency) ?>
                                            <?php endif; ?>
                                            <?php if ((int) $category['salidas'] > 0): ?>
                                                −<?= money_html((int) $category['salidas'], $currency) ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= money_html((int) $category['ajustado'], $currency) ?></td>
                                <td class="text-end"><?= money_html((int) $category['gastado'], $currency) ?></td>
                                <td class="text-end fw-semibold"><?= money_html((int) $category['disponible'], $currency) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-secondary"
                                       href="/categorias/ver?parte=<?= rawurlencode((string) $category['nombre']) ?>">
                                        <?= e(__('moves.view_category')) ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="surface-card">
    <h2 class="h5 mb-3"><?= e(__('moves.recent')) ?></h2>

    <?php if ($moves === []): ?>
        <p class="text-secondary small mb-0"><?= e(__('moves.empty')) ?></p>
    <?php else: ?>
        <div class="finance-list">
            <?php foreach ($moves as $move): ?>
                <div class="finance-row">
                    <div class="flex-grow-1">
                        <span class="fw-semibold d-block">
                            <?= e((string) $move['origen']) ?> → <?= e((string) $move['destino']) ?>
                        </span>
                        <span class="small text-secondary">
                            <?= e(__('moves.reasons.' . (string) $move['motivo_catalogo'])) ?>
                            · <?= e(format_datetime($move['fecha'] ?? null)) ?>
                            <?php if (!empty($move['motivo_texto'])): ?>
                                · <?= e((string) $move['motivo_texto']) ?>
                            <?php endif; ?>
                        </span>
                    </div>
                    <span class="fw-semibold"><?= money_html((int) $move['monto_centavos'], (string) $move['moneda']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
